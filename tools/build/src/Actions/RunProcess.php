<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use IteratorAggregate;
use Ramona\AutomationPlatformLibBuild\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Symfony\Component\Process\Process;

/**
 * @api
 */
final class RunProcess implements BuildAction
{
    public function __construct(private string $command)
    {
    }

    public function execute(callable $onOutputLine, callable $onErrorLine): BuildActionResult
    {
        $process = Process::fromShellCommandline($this->command);
        $process->start();

        /** @psalm-var IteratorAggregate<string, string> $process  */

        foreach ($process as $type => $data) {
            if ($type === Process::OUT) {
                $onOutputLine($data);
            } else {
                $onErrorLine($data);
            }
        }

        /** @psalm-var Process $process */

        $exitCode = $process->getExitCode();
        return $exitCode === 0
            ? BuildActionResult::ok()
            : BuildActionResult::fail("Failed to execute command \"{$this->command}\" - exit code {$exitCode}");
    }
}
