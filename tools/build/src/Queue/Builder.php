<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Queue;

use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\Targets\TargetQueue;

final class Builder
{
    public function __construct(private BuildDefinitionsLoader $buildDefinitions)
    {
    }

    public function build(TargetId $desiredTarget): TargetQueue
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
}
