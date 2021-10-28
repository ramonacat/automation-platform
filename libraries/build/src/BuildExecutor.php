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
            $toBuild = $leftToBuild->dequeue();
            $target = $this->buildDefinitions->target($toBuild);

            $allDependenciesAdded = true;
            foreach ($target->dependencies() as $targetDependency) {
                if (!$buildQueue->hasId($targetDependency->id())) {
                    $leftToBuild->enqueue($targetDependency);
                    $allDependenciesAdded = false;
                }
            }

            if ($allDependenciesAdded) {
                if (!$buildQueue->hasId($toBuild->id())) {
                    $buildQueue->enqueue($toBuild);
                }
            } else {
                if ($leftToBuild->hasId($toBuild->id())) {
                    throw CyclicDependencyFound::at($toBuild->id());
                }

                $leftToBuild->enqueue($toBuild);
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
