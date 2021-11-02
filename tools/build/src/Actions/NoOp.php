<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;

/**
 * @api
 */
final class NoOp implements BuildAction
{
    public function execute(callable $onOutputLine, callable $onErrorLine): BuildActionResult
    {
        return BuildActionResult::ok();
    }
}
