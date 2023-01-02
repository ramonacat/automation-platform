<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildFacts;

final class BuildFactsTest extends TestCase
{
    public function testHasBuildId(): void
    {
        $facts = new BuildFacts('test', false, 1, 1, 'main');

        self::assertSame('test', $facts->buildId());
    }

    public function testHasLogicalCores(): void
    {
        $facts = new BuildFacts('test', false, 1, 2, 'main');

        self::assertSame(1, $facts->logicalCores());
    }

    public function testHasPhysicalCores(): void
    {
        $facts = new BuildFacts('test', false, 1, 2, 'main');

        self::assertSame(2, $facts->physicalCores());
    }

    public function testHasPipelineStatus(): void
    {
        $facts = new BuildFacts('test', true, 1, 2, 'main');

        self::assertTrue($facts->inPipeline());
    }

    public function testHasBaseReference(): void
    {
        $facts = new BuildFacts('test', false, 1, 2, 'main');

        self::assertSame('main', $facts->baseReference());
    }
}
