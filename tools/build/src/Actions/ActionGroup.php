<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

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

    public function execute(ActionOutput $output, Configuration $configuration): BuildActionResult
    {
        foreach ($this->actions as $action) {
            $result = $action->execute($output, $configuration);

            if (!$result->hasSucceeded()) {
                return $result;
            }
        }

        return BuildActionResult::ok();
    }
}
