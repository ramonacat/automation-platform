<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Processes\DefaultProcessBuilder;

final class ContextFactory
{
    public static function create(?Configuration $configuration = null, ?Collector $artifactCollector = null, ?BuildFacts $buildFacts = null): Context
    {
        $configuration = $configuration ?? Configuration::fromJsonString('{}');
        $artifactCollector = $artifactCollector ?? new Collector();
        $buildFacts = $buildFacts ?? new BuildFacts('test', null, 1, 1);

        return new Context($configuration, $artifactCollector, $buildFacts, new DefaultProcessBuilder());
    }
}
