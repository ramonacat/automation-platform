<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Context;

/**
 * @api
 */
final class Group implements BuildAction
{
    /**
     * @param non-empty-list<BuildAction> $actions
     */
    public function __construct(private array $actions)
    {
    }

    public function execute(ActionOutput $output, Context $context): BuildActionResult
    {
        $artifacts = [];
        foreach ($this->actions as $action) {
            $result = $action->execute($output, $context);

            if (!$result->hasSucceeded()) {
                return $result;
            }

            foreach ($result->artifacts() as $artifact) {
                $artifacts[] = $artifact;
            }
        }

        return BuildActionResult::ok($artifacts);
    }
}
