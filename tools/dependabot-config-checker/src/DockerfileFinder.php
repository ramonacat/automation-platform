<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformToolDependabotConfigChecker;

use function assert;
use const DIRECTORY_SEPARATOR;
use function dirname;
use function in_array;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

final class DockerfileFinder
{
    /**
     * @return list<string>
     */
    public static function find(): array
    {
        $basePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR;
        $recursiveDirectoryIterator = new RecursiveDirectoryIterator($basePath);
        $filter = new RecursiveCallbackFilterIterator($recursiveDirectoryIterator, function (SplFileInfo|string $item) {
            assert($item instanceof SplFileInfo);
            return !($item->isDir() && in_array($item->getFilename(), ['vendor', 'target'], true));
        });
        $iterator = new RecursiveIteratorIterator($filter);

        $result = [];
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $basename = $item->getBasename();
            if ($basename === 'Dockerfile' || str_starts_with($basename, 'Dockerfile.') || str_ends_with($basename, '.Dockerfile')) {
                $realPath = $item->getRealPath();

                if ($realPath === false) {
                    throw new RuntimeException('Failed to get the realpath for ' . $item->getPath());
                }

                $result[] = str_replace([$basePath, DIRECTORY_SEPARATOR], ['', '/'], $realPath);
            }
        }

        return $result;
    }
}
