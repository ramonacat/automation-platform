<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

use RuntimeException;

final class ArtifactKeyAlreadyUsed extends RuntimeException
{
    public function __construct(string $path, string $key)
    {
        parent::__construct("The artifact key \"$key\" was already used in the build-config in: \"{$path}\"");
    }
}
