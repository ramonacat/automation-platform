<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

/**
 * @psalm-immutable
 */
final class TargetId
{
    private string $path;
    private string $target;

    public function __construct(string $path, string $target)
    {
        $this->path = $path;
        $this->target = $target;
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
