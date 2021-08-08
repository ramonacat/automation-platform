<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function file_put_contents;

final class PutFile implements BuildAction
{
    public function __construct(private string $path, private string $contents)
    {
    }

    public function execute(): BuildActionResult
    {
        if (file_put_contents($this->path, $this->contents) !== false) {
            return BuildActionResult::ok();
        }

        return BuildActionResult::fail("Failed to put file \"{$this->path}\"");
    }
}
