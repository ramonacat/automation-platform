<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use function copy;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

/**
 * @api
 */
final class CopyFile implements BuildAction
{
    public function __construct(private string $source, private string $target)
    {
    }

    public function execute(ActionOutput $output, Configuration $configuration): BuildActionResult
    {
        if (!copy($this->source, $this->target)) {
            return BuildActionResult::fail("Failed to copy \"{$this->source}\" to \"{$this->target}\"");
        }

        return BuildActionResult::ok();
    }
}
