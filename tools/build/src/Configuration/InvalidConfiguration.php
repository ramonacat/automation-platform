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
}
