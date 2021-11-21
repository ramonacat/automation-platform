<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformToolDependabotConfigChecker;

interface CheckerOutput
{
    public function invalid(string $message): void;
}
