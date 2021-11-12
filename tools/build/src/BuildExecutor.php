<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

final class BuildExecutor
{
    public function __construct(
        private LoggerInterface        $logger,
        private BuildOutput            $buildOutput,
        private BuildDefinitionsLoader $buildDefinitions,
        private Configuration          $configuration
    ) {
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
                    $this->configuration
                )
            );

            // fixme log these from the callbacks
            $standardOutput = $this->buildOutput->getCollectedStandardOutput();
            $standardError = $this->buildOutput->getCollectedStandardError();

            $this->buildOutput->finalizeTarget($targetId, $result);

            if (!$result->hasSucceeded()) {
                return $result;
            }

            $this->logger->info('Target built', ['target-id' => $targetId->toString(), 'stdout' => $standardOutput, 'stderr' => $standardError]);
        }

        return BuildActionResult::ok();
    }
}
