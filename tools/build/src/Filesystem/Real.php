<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Filesystem;

use function file_get_contents;
use function realpath;
use RuntimeException;
use function sprintf;

final class Real implements Filesystem
{
    public function readFile(string $path): string
    {
        $result = file_get_contents($path);

        if ($result === false) {
            // TODO: FileNotReadableException
            throw new RuntimeException(sprintf('Could not read file "%s"', $path));
        }

        return $result;
    }

    public function realpath(string $path): string
    {
        $result = realpath($path);

        if ($result === false) {
            // TODO: RealPathNotFoundException
            throw new RuntimeException(sprintf('Could not find the realpath for file "%s"', $path));
        }

        return $result;
    }
}
