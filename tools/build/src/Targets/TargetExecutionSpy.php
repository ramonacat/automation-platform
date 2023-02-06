<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use Ramona\AutomationPlatformLibBuild\BuildResult;

interface TargetExecutionSpy
{
    /**
     * @param list<TargetId> $dependencies
     */
    public function targetStarted(TargetId $targetId, array $dependencies): void;
    public function targetFinished(TargetId $targetId, BuildResult $result): void;
    public function buildFinished(): void;
}
