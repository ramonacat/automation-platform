<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use RuntimeException;
use function sprintf;

final class InvalidBuildDefinition extends RuntimeException
{
    public static function atPath(string $path): self
    {
        return new self(sprintf('Invalid build definition at path "%s"', $path));
    }
}
