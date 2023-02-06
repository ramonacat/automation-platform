<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use Ramona\AutomationPlatformLibBuild\BuildResult;

final class NullTargetExecutionSpy implements TargetExecutionSpy
{
    public function targetStarted(TargetId $targetId, array $dependencies): void
    {
    }

    public function targetFinished(TargetId $targetId, BuildResult $result): void
    {
    }

    public function buildFinished(): void
    {
    }
}
