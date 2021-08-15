<?php

namespace Ramona\AutomationPlatformLibBuild;

final class CopyFile implements BuildAction
{
    public function __construct(private string $source, private string $target){}

    public function execute(): BuildActionResult
    {
        if(!copy($this->source, $this->target)) {
            return BuildActionResult::fail("Failed to copy \"{$this->source}\" to \"{$this->target}\"");
        }

        return BuildActionResult::ok();
    }
}