<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Context;
use function Safe\copy;

/**
 * @api
 */
final class CopyFile implements BuildAction
{
    public function __construct(private string $source, private string $target)
    {
    }

    public function execute(ActionOutput $output, Context $context): BuildActionResult
    {
        copy($this->source, $this->target);

        return BuildActionResult::ok([]);
    }
}
