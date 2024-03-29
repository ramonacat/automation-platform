<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Processes;

use Fiber;
use IteratorAggregate;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Symfony\Component\Process\Process;

final class DefaultInActionProcess implements InActionProcess
{
    /**
     * @param list<string> $command
     * @param int $timeout
     * @param array<string, string> $additionalEnvironmentVariables
     */
    public function __construct(
        private string $workingDirectory,
        private array $command,
        private int $timeout,
        private array $additionalEnvironmentVariables = []
    ) {
    }

    public function run(TargetOutput $output, string $standardIn = ''): bool
    {
        $process = new Process(
            $this->command,
            $this->workingDirectory,
            env: $this->additionalEnvironmentVariables + $_ENV
        );

        $process->setTimeout($this->timeout);
        $process->setInput($standardIn);
        $process->start();

        Fiber::suspend();

        $iter = $process->getIterator(Process::ITER_NON_BLOCKING);
        /** @psalm-var IteratorAggregate<string, string> $iter  */

        foreach ($iter as $type => $data) {
            if ($type === Process::OUT) {
                $output->pushOutput($data);
            } else {
                $output->pushError($data);
            }

            Fiber::suspend();
        }

        $exitCode = $process->getExitCode();
        return $exitCode === 0;
    }
}
