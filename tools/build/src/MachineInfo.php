<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use const PHP_OS;
use Symfony\Component\Process\Process;
use function trim;

final class MachineInfo
{
    public function logicalCores(): int
    {
        return $this->getInt('logical-cores');
    }

    public function physicalCores(): int
    {
        return $this->getInt('physical-cores');
    }

    private function getInt(string $action): int
    {
        $process = new Process([__DIR__ . '/../../machine-info/target/release/machine-info' . (PHP_OS === 'Windows' ? '.exe' : ''), $action]);
        $process->mustRun();

        // todo validate the int instead of just casting
        return (int)trim($process->getOutput());
    }
}
