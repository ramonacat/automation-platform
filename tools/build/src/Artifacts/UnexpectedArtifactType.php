<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

use function get_class;
use RuntimeException;

final class UnexpectedArtifactType extends RuntimeException
{
    /**
     * @param class-string<Artifact> $expectedType
     */
    public static function fromArtifact(string $expectedType, Artifact $artifact): self
    {
        return new self("Expected an artifact of type $expectedType, got " . get_class($artifact));
    }
}
