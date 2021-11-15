<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

final class BuildFacts
{
    public function __construct(private string $buildId)
    {
    }

    public function buildId(): string
    {
        return $this->buildId;
    }
}
