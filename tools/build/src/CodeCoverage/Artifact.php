<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CodeCoverage;

use Ramona\AutomationPlatformLibBuild\Artifacts\DisplayName;

#[DisplayName('Code Coverage')]
final class Artifact implements \Ramona\AutomationPlatformLibBuild\Artifacts\Artifact
{
    public function __construct(private string $key, private string $path, private Kind $kind)
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->path;
    }

    public function kind(): Kind
    {
        return $this->kind;
    }
}
