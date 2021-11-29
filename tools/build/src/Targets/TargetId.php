<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use function Safe\realpath;
use function strrpos;
use function substr;

final class TargetId
{
    private string $path;
    private string $target;

    public function __construct(string $path, string $target)
    {
        $this->path = realpath($path);
        $this->target = $target;
    }

    public static function fromString(string $raw): self
    {
        $lastColonIndex = strrpos($raw, ':');

        if ($lastColonIndex === false) {
            throw FailedToParseTargetId::fromRaw($raw);
        }

        $path = substr($raw, 0, $lastColonIndex);
        $target = substr($raw, $lastColonIndex + 1);

        return new self($path, $target);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function target(): string
    {
        return $this->target;
    }

    public function toString(): string
    {
        return "{$this->path}:{$this->target}";
    }
}
