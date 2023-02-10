<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Processes\DefaultProcessBuilder;

final class ContextTest extends TestCase
{
    public function testHasConfiguration(): void
    {
        $context = new Context(
            $config = Configuration::fromJsonString('{}'),
            new Collector(),
            $this->createBuildFacts(),
            new DefaultProcessBuilder()
        );

        self::assertSame($config, $context->configuration());
    }

    public function testHasArtifactCollector(): void
    {
        $context = new Context(
            Configuration::fromJsonString('{}'),
            $collector = new Collector(),
            $this->createBuildFacts(),
            new DefaultProcessBuilder()
        );

        self::assertSame($collector, $context->artifactCollector());
    }

    public function testHasBuildFacts(): void
    {
        $context = new Context(
            Configuration::fromJsonString('{}'),
            new Collector(),
            $facts = $this->createBuildFacts(),
            new DefaultProcessBuilder()
        );

        self::assertSame($facts, $context->buildFacts());
    }

    private function createBuildFacts(): BuildFacts
    {
        return new BuildFacts('test', null, 1, 1);
    }
}
