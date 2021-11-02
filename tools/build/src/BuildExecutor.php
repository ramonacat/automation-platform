<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function Safe\chdir;
use function Safe\getcwd;

final class BuildExecutor
{
    public function __construct(private BuildDefinitionsLoader $buildDefinitions)
    {
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
                    if(!$leftToBuild->hasId($targetDependency)) {
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

    public function executeTarget(string $workingDirectory, string $name): BuildActionResult
    {
        $queue = $this->buildQueue(new TargetId($workingDirectory, $name));

        while (!$queue->isEmpty()) {
            $targetId = $queue->dequeue();
            $target = $this->buildDefinitions->target($targetId);

            $result = $this->inWorkingDirectory($targetId->path(), fn () => $target->execute());

            if (!$result->hasSucceeded()) {
                return $result;
            }
        }

        return BuildActionResult::ok();
    }

    /**
     * todo this should probably be placed elsewhere
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function inWorkingDirectory(string $workingDirectory, callable $callback)
    {
        $currentWorkingDirectory = getcwd();
        chdir($workingDirectory);
        try {
            $result = ($callback)();
        } finally {
            chdir($currentWorkingDirectory);
        }

        return $result;
    }
}
