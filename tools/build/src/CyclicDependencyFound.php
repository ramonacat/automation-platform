<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use RuntimeException;

final class CyclicDependencyFound extends RuntimeException
{
    public static function at(string $where): self
    {
        return new self("Cyclic dependency found: $where");
    }
}
