<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use Fiber;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\BuildOutput\BuildOutput;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\Targets\TargetQueue;

final class BuildExecutor
{
    private Collector $artifactCollector;

    public function __construct(
        private LoggerInterface        $logger,
        private BuildOutput            $buildOutput,
        private BuildDefinitionsLoader $buildDefinitions,
        private Configuration          $configuration,
        private BuildFacts             $buildFacts
    ) {
        $this->artifactCollector = new Collector();
    }

    /**
     * @todo extract this to its own class?
     */
    public function buildQueue(TargetId $desiredTarget): TargetQueue
    {
        $leftToBuild = new TargetQueue();
        $leftToBuild->enqueue($desiredTarget);

        $buildQueue = new TargetQueue();

        while (!$leftToBuild->isEmpty()) {
            $targetId = $leftToBuild->dequeue();
            $dependencies = $this->buildDefinitions->target($targetId)->dependencies();

            $allDependenciesQueued = true;

            foreach ($dependencies as $targetDependency) {
                $isInBuildQueue = $buildQueue->hasId($targetDependency);

                if (!$isInBuildQueue) {
                    if (!$leftToBuild->hasId($targetDependency)) {
                        $leftToBuild->enqueue($targetDependency);
                    }

                    $allDependenciesQueued = false;
                }
            }

            if ($allDependenciesQueued) {
                $buildQueue->enqueue($targetId);
            } else {
                $leftToBuild->enqueue($targetId);
            }
        }

        return $buildQueue;
    }

    public function executeTarget(TargetId $targetId): BuildActionResult
    {
        $context = new Context(
            $this->configuration,
            $this->artifactCollector,
            $this->buildFacts
        );

        $queue = $this->buildQueue($targetId);
        $this->buildOutput->setTargetCount($queue->count());

        while (!$queue->isEmpty()) {
            $targetId = $queue->dequeue();
            $target = $this->buildDefinitions->target($targetId);

            $this->buildOutput->startTarget($targetId);

            $fiber = new Fiber(function () use ($target, $context, $targetId) {
                $result = $target->execute($this->buildOutput, $context, $targetId->path());
                Fiber::suspend($result);
            });
            /** @var BuildActionResult|null $result */
            $result = $fiber->start();

            while ($result === null) {
                /** @var BuildActionResult|null $result */
                $result = $fiber->resume();
            }

            $standardOutput = $this->buildOutput->getCollectedStandardOutput();
            $standardError = $this->buildOutput->getCollectedStandardError();

            $this->buildOutput->finalizeTarget($targetId, $result);

            if (!$result->hasSucceeded()) {
                return $result;
            }

            foreach ($result->artifacts() as $artifact) {
                $this->artifactCollector->collect($targetId, $artifact);
            }

            $this->logger->info('Target built', ['target-id' => $targetId->toString(), 'stdout' => $standardOutput, 'stderr' => $standardError]);
        }

        return BuildActionResult::ok($this->artifactCollector->all());
    }
}
