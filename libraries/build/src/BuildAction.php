<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

interface BuildAction
{
    public function execute(): BuildActionResult;
}
