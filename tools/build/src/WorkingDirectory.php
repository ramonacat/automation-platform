<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function Safe\chdir;
use function Safe\getcwd;

final class WorkingDirectory
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function in(string $workingDirectory, callable $callback)
    {
        $currentWorkingDirectory = getcwd();
        chdir($workingDirectory);
        try {
            $result = ($callback)();
        } finally {
            chdir($currentWorkingDirectory);
        }

        return $result;
    }
}
