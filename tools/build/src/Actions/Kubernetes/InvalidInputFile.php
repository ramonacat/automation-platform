<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Kubernetes;

use RuntimeException;

final class InvalidInputFile extends RuntimeException
{
    public static function notAnArray(string $path): self
    {
        return new self("The file at $path is not an array");
    }
}
