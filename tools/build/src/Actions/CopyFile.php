<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use function copy;
use Ramona\AutomationPlatformLibBuild\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;

/**
 * @api
 */
final class CopyFile implements BuildAction
{
    public function __construct(private string $source, private string $target)
    {
    }

    public function execute(callable $onOutputLine, callable $onErrorLine): BuildActionResult
    {
        if (!copy($this->source, $this->target)) {
            return BuildActionResult::fail("Failed to copy \"{$this->source}\" to \"{$this->target}\"");
        }

        return BuildActionResult::ok();
    }
}
