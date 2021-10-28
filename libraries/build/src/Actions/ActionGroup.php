<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;

/**
 * @api
 */
final class ActionGroup implements BuildAction
{
    /**
     * @param non-empty-list<BuildAction> $actions
     */
    public function __construct(private array $actions)
    {
    }

    public function execute(): BuildActionResult
    {
        foreach ($this->actions as $action) {
            $result = $action->execute();

            if (!$result->hasSucceeded()) {
                return $result;
            }
        }

        return BuildActionResult::ok();
    }
}
