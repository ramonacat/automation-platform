<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Processes;

use Fiber;
use IteratorAggregate;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Symfony\Component\Process\Process;

final class InActionProcess
{
    /**
     * @param list<string> $command
     * @param int $timeout
     */
    public function __construct(private string $workingDirectory, private array $command, private int $timeout)
    {
    }

    public function run(TargetOutput $output, string $standardIn = ''): bool
    {
        $process = new Process($this->command, $this->workingDirectory);

        $process->setTimeout($this->timeout);
        $process->setInput($standardIn);
        $process->start();

        Fiber::suspend();

        /** @psalm-var IteratorAggregate<string, string> $process  */

        foreach ($process as $type => $data) {
            if ($type === Process::OUT) {
                $output->pushOutput($data);
            } else {
                $output->pushError($data);
            }

            Fiber::suspend();
        }

        /** @psalm-var Process $process */
        $exitCode = $process->getExitCode();
        return $exitCode === 0;
    }
}
