<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CodeCoverage;

final class State
{
    /**
     * @var array<string, float>
     */
    private array $coverages = [];

    public function addEntry(string $name, float $coverage): void
    {
        $this->coverages[$name] = $coverage;
    }

    /**
     * @return array<string, float>
     */
    public function coverages(): array
    {
        return $this->coverages;
    }
}
