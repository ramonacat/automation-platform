<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use const PHP_OS;
use Symfony\Component\Process\Process;

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
        // todo validate the int instead of just casting
        return (int)$this->getString($action);
    }

    private function getString(string $action): string
    {
        $process = new Process([__DIR__ . '/../../machine-info/target/release/machine-info' . (PHP_OS === 'Windows' ? '.exe' : ''), $action]);
        $process->setPty(true);

        $process->mustRun();

        return $process->getOutput();
    }
}
