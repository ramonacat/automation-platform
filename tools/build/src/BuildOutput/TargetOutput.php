<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Ramona\AutomationPlatformLibBuild\BuildResult;

interface TargetOutput
{
    public function pushError(string $data): void;
    public function pushOutput(string $data): void;

    public function getCollectedStandardOutput(): string;
    public function getCollectedStandardError(): string;

    public function finalize(BuildResult $result): void;
}
