<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildFacts;

final class BuildFactsTest extends TestCase
{
    public function testHasBuildId(): void
    {
        $facts = new BuildFacts('test', false);

        self::assertSame('test', $facts->buildId());
    }
}
