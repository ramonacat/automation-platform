<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Processes;

use IteratorAggregate;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Symfony\Component\Process\Process;

final class InActionProcess
{
    /**
     * @param list<string> $command
     * @param int $timeout
     */
    public function __construct(private array $command, private int $timeout)
    {
    }

    public function run(ActionOutput $output): bool
    {
        $process = new Process($this->command);
        // todo nicely formatted time interval, once we have the infra for that
        $output->pushSeparator('Running: ' . $process->getCommandLine() . ' with a timeout of ' . (string)$this->timeout . 's');
        $process->setTimeout($this->timeout);
        $process->start();

        /** @psalm-var IteratorAggregate<string, string> $process  */

        foreach ($process as $type => $data) {
            if ($type === Process::OUT) {
                $output->pushOutput($data);
            } else {
                $output->pushError($data);
            }
        }

        /** @psalm-var Process $process */
        $exitCode = $process->getExitCode();
        return $exitCode === 0;
    }
}
