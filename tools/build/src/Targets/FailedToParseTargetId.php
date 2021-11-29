<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use RuntimeException;

final class FailedToParseTargetId extends RuntimeException
{
    public static function fromRaw(string $raw): self
    {
        return new self("Failed to parse \"{$raw}\" as a target id");
    }
}
