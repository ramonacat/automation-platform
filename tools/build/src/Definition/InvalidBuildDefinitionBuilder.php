<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use RuntimeException;

final class InvalidBuildDefinitionBuilder extends RuntimeException
{
    public static function noTargets(): self
    {
        return new self('No build targets were provided');
    }
}
