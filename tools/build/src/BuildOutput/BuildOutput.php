<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

interface BuildOutput
{
    public function startTarget(TargetId $id): TargetOutput;

    /**
     * @param array<string, array{0:BuildResult,1:TargetOutput}> $results
     */
    public function finalizeBuild(array $results): void;
}
