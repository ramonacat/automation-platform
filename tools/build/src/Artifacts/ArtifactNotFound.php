<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

use RuntimeException;

final class ArtifactNotFound extends RuntimeException
{
    public function __construct(string $directory, string $key)
    {
        parent::__construct("Artifact \"$key\" not found in \"$directory\"");
    }
}
