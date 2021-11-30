<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function array_map;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\BuildOutput\BuildOutput;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Targets\Parallel\FiberTargetExecutor;
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

        $this->logger->info(
            'Starting a build',
            [
                'targetId' => $targetId->toString(),
                'queue' => array_map(
                    static fn (TargetId $t) => $t->toString(),
                    $queue->asArray()
                )
            ]
        );

        $targetFiberStack = new FiberTargetExecutor(
            $this->buildFacts->logicalCores(),
            $this->artifactCollector,
            $this->logger
        );

        while (!$queue->isEmpty()) {
            $targetId = $queue->dequeue();
            $target = $this->buildDefinitions->target($targetId);

            $targetFiberStack->addTarget($targetId, $target, $this->buildOutput->startTarget($targetId), $context);
        }

        $results = $targetFiberStack->waitForAll();

        $this->buildOutput->finalizeBuild($results);

        $failed = false;
        foreach ($results as $result) {
            if (!$result[0]->hasSucceeded()) {
                $failed = true;
                break;
            }
        }

        return $failed ? BuildActionResult::fail('Build failed') : BuildActionResult::ok($this->artifactCollector->all());
    }
}
