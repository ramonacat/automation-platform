<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use RuntimeException;
use function sprintf;

final class ConfigurationValueNotFound extends RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('There\'s no value at key "%s"', $path));
    }
}
