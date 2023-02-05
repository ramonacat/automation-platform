<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CodeCoverage;

use RuntimeException;
use function sprintf;

final class InvalidCoverageFile extends RuntimeException
{
    public static function cannotDecode(string $path): self
    {
        return new self(sprintf('Could not decode file "%s"', $path));
    }

    public static function noKeyInFile(string $path, string $key): self
    {
        return new self(sprintf('Could not find key "%s" in file "%s"', $key, $path));
    }
}
