<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformToolDependabotConfigChecker;

use function fwrite;
use const PHP_EOL;
use const STDERR;

final class DefaultCheckerOutput implements CheckerOutput
{
    public function invalid(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
