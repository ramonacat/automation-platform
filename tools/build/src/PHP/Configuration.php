<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

final class Configuration
{
    public function __construct(
        private int $minMsi = 100,
        private int $minCoveredMsi = 100
    ) {
    }

    public function minMsi(): int
    {
        return $this->minMsi;
    }

    public function minCoveredMsi(): int
    {
        return $this->minCoveredMsi;
    }
}
