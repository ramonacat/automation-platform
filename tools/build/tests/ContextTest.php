<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;

final class ContextTest extends TestCase
{
    public function testHasConfiguration(): void
    {
        $context = new Context(
            $config = Configuration::fromJsonString('{}'),
            new Collector(),
            $this->createBuildFacts()
        );

        self::assertSame($config, $context->configuration());
    }

    public function testHasArtifactCollector(): void
    {
        $context = new Context(
            Configuration::fromJsonString('{}'),
            $collector = new Collector(),
            $this->createBuildFacts()
        );

        self::assertSame($collector, $context->artifactCollector());
    }

    public function testHasBuildFacts(): void
    {
        $context = new Context(
            Configuration::fromJsonString('{}'),
            new Collector(),
            $facts = $this->createBuildFacts()
        );

        self::assertSame($facts, $context->buildFacts());
    }

    private function createBuildFacts(): BuildFacts
    {
        return new BuildFacts('test', false, 1, 1);
    }
}
