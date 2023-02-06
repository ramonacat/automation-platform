<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Filesystem;

use function file_get_contents;
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
}
