<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function Safe\realpath;

final class TargetId
{
    private string $path;
    private string $target;

    public function __construct(string $path, string $target)
    {
        $this->path = realpath($path);
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
