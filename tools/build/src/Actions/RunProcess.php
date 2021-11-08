<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use IteratorAggregate;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Symfony\Component\Process\Process;

/**
 * @api
 */
final class RunProcess implements BuildAction
{
    // todo lower the default timeout once the docker builds get their own action
    private const DEFAULT_TIMEOUT = 60 * 30;

    public function __construct(private string $command, private int $timeoutSeconds = self::DEFAULT_TIMEOUT)
    {
    }

    public function execute(ActionOutput $output, Configuration $configuration): BuildActionResult
    {
        $process = Process::fromShellCommandline($this->command);
        $process->setTimeout($this->timeoutSeconds);
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
        return $exitCode === 0
            ? BuildActionResult::ok()
            : BuildActionResult::fail("Failed to execute command \"{$this->command}\" - exit code {$exitCode}");
    }
}
