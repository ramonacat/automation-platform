<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Ramona\AutomationPlatformLibBuild\BuildResult;

final class NullTargetOutput implements TargetOutput
{
    public function pushError(string $data): void
    {
        // todo split TargetOutput and CollectedTargetOutput, so we don't have to leave empty methods here?
    }

    public function pushOutput(string $data): void
    {
        // todo split TargetOutput and CollectedTargetOutput, so we don't have to leave empty methods here?
    }

    public function getCollectedStandardOutput(): string
    {
        return '';
    }

    public function getCollectedStandardError(): string
    {
        return '';
    }

    public function finalize(BuildResult $result): void
    {
        // todo split TargetOutput and CollectedTargetOutput, so we don't have to leave empty methods here?
    }
}
