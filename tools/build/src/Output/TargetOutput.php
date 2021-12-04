<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Output;

use Ramona\AutomationPlatformLibBuild\BuildResult;

interface TargetOutput
{
    public function pushError(string $data): void;
    public function pushOutput(string $data): void;

    public function finalize(BuildResult $result): CollectedTargetOutput;
}
