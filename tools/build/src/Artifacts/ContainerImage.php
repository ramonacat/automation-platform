<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

#[DisplayName('Container Image')]
final class ContainerImage implements Artifact
{
    public function __construct(private string $key, private string $name, private string $tag)
    {
    }

    public function name(): string
    {
        return "{$this->name}:{$this->tag}";
    }

    public function key(): string
    {
        return $this->key;
    }
}
