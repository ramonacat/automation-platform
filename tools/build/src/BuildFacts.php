<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Ramona\AutomationPlatformLibBuild\CI\State;

final class BuildFacts
{
    public function __construct(
        private string $buildId,
        private ?State $ciState,
        private int $logicalCores,
        private int $physicalCores
    ) {
    }

    public function buildId(): string
    {
        return $this->buildId;
    }

    public function ciState(): ?State
    {
        return $this->ciState;
    }

    public function logicalCores(): int
    {
        return $this->logicalCores;
    }

    public function physicalCores(): int
    {
        return $this->physicalCores;
    }
}
