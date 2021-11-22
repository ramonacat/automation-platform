<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\PHP;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\PHP\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsTo100MinMsi(): void
    {
        $configuration = new Configuration();

        self::assertSame(100, $configuration->minMsi());
    }

    public function testDefaultsTo100MinCoveredMsi(): void
    {
        $configuration = new Configuration();

        self::assertSame(100, $configuration->minCoveredMsi());
    }
}
