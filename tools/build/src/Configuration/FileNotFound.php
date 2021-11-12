<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use RuntimeException;

final class FileNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('The configuration file was not found');
    }
}
