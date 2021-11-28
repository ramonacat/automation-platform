<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildFacts;

final class BuildFactsTest extends TestCase
{
    public function testHasBuildId(): void
    {
        $facts = new BuildFacts('test', false, 1, 1);

        self::assertSame('test', $facts->buildId());
    }

    public function testHasLogicalCores(): void
    {
        $facts = new BuildFacts('test', false, 1, 2);

        self::assertSame(1, $facts->logicalCores());
    }

    public function testHasPhysicalCores(): void
    {
        $facts = new BuildFacts('test', false, 1, 2);

        self::assertSame(2, $facts->physicalCores());
    }
}
