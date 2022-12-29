<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

final class BuildFacts
{
    public function __construct(
        private string $buildId,
        private bool $inPipeline,
        private int $logicalCores,
        private int $physicalCores,
        // TODO: Remove the git references from here and make it part of the git state or something
        private string $baseReference
    ) {
    }

    public function buildId(): string
    {
        return $this->buildId;
    }

    public function inPipeline(): bool
    {
        return $this->inPipeline;
    }

    public function logicalCores(): int
    {
        return $this->logicalCores;
    }

    public function physicalCores(): int
    {
        return $this->physicalCores;
    }

    public function baseReference(): string
    {
        return $this->baseReference;
    }
}
