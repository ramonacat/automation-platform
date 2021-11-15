<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildOutput\BuildOutput;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use function str_replace;
use function uniqid;

final class BuildExecutor
{
    private Collector $artifactCollector;

    public function __construct(
        private LoggerInterface        $logger,
        private BuildOutput            $buildOutput,
        private BuildDefinitionsLoader $buildDefinitions,
        private Configuration          $configuration
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
            new BuildFacts(str_replace('.', '', uniqid('', true)))// todo move this outta here, use something like git tag as the ID
        );

        $queue = $this->buildQueue($targetId);
        $this->buildOutput->setTargetCount($queue->count());

        while (!$queue->isEmpty()) {
            $targetId = $queue->dequeue();
            $target = $this->buildDefinitions->target($targetId);

            $this->buildOutput->startTarget($targetId);

            $result = WorkingDirectory::in(
                $targetId->path(),
                fn () => $target->execute(
                    $this->buildOutput,
                    $context
                )
            );

            // fixme log these from the callbacks
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
