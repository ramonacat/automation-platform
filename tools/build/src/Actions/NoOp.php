<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Context;

/**
 * @api
 */
final class NoOp implements BuildAction
{
    public function execute(ActionOutput $output, Context $context): BuildActionResult
    {
        return BuildActionResult::ok([]);
    }
}
