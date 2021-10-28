<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use function passthru;
use Ramona\AutomationPlatformLibBuild\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;

/**
 * @api
 */
final class RunProcess implements BuildAction
{
    public function __construct(private string $command)
    {
    }

    public function execute(): BuildActionResult
    {
        passthru($this->command, $exitCode);

        return $exitCode === 0
            ? BuildActionResult::ok()
            : BuildActionResult::fail("Failed to execute command \"{$this->command}\" - exit code {$exitCode}");
    }
}
