<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

final class Context
{
    public function __construct(
        private Configuration $configuration,
        private Collector $artifactCollector,
        private BuildFacts $buildFacts
    ) {
    }

    public function configuration(): Configuration
    {
        return $this->configuration;
    }

    public function artifactCollector(): Collector
    {
        return $this->artifactCollector;
    }

    public function buildFacts(): BuildFacts
    {
        return $this->buildFacts;
    }
}
