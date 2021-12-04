<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use RuntimeException;

final class InvalidConfiguration extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function missingBuildKey(): self
    {
        return new self('The "build" key is missing');
    }

    public static function notAnArray(): self
    {
        return new self("The configuration given does not parse to an array");
    }

    public static function buildNotAnArray(): self
    {
        return new self("The build key in configuration does not parse to an array");
    }

    public static function runtimeNotAnArray(): self
    {
        return new self("The runtime key in the configuration is not an array");
    }
}
