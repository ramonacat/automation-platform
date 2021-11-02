<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

interface BuildAction
{
    /**
     * @param callable(string):void $onOutputLine
     * @param callable(string):void $onErrorLine
     */
    public function execute(callable $onOutputLine, callable $onErrorLine): BuildActionResult;
}
