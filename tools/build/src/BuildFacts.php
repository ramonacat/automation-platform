<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

final class BuildFacts
{
    public function __construct(private string $buildId, private bool $inPipeline)
    {
    }

    public function buildId(): string
    {
        return $this->buildId;
    }

    public function inPipeline(): bool
    {
        return $this->inPipeline;
    }
}
