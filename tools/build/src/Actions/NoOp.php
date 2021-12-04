<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;

/**
 * @api
 */
final class NoOp implements BuildAction
{
    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        return BuildResult::ok([]);
    }
}
