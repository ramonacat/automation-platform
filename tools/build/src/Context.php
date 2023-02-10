<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Processes\ProcessBuilder;

final class Context
{
    public function __construct(
        private Configuration $configuration,
        private Collector $artifactCollector,
        private BuildFacts $buildFacts,
        // TODO this ideally should not be a part of the context, but instead DIed into the actions, but that requires a bigger architectural change
        private ProcessBuilder $processBuilder
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

    public function processBuilder(): ProcessBuilder
    {
        return $this->processBuilder;
    }
}
