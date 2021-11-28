<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Context;

/**
 * @api
 */
final class NoOp implements BuildAction
{
    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildActionResult
    {
        return BuildActionResult::ok([]);
    }
}
