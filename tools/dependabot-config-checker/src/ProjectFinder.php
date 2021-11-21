<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformToolDependabotConfigChecker;

use function array_merge;
use const DIRECTORY_SEPARATOR;
use function dirname;
use function glob;
use const GLOB_ONLYDIR;
use function ltrim;
use function Safe\realpath;
use function str_replace;

final class ProjectFinder
{
    /**
     * @return list<string>
     */
    public static function find(): array
    {
        $result = ['/'];

        $root = realpath(dirname(__DIR__, 3));

        $result = array_merge($result, self::findIn($root, 'tools'));
        $result = array_merge($result, self::findIn($root, 'services'));

        return array_merge($result, self::findIn($root, 'libraries' . DIRECTORY_SEPARATOR . '*'));
    }

    /**
     * @return list<string>
     */
    private static function findIn(string $root, string $pattern): array
    {
        $result = [];

        foreach (glob($root . DIRECTORY_SEPARATOR . $pattern . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $tool) {
            $entry = str_replace([$root, DIRECTORY_SEPARATOR], ['', '/'], realpath($tool)) . '/';

            if ($entry !== '/') {
                $entry = ltrim($entry, '/');
            }

            $result[] = $entry;
        }

        return $result;
    }
}
