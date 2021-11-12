<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testCanGetSingleValue(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a" : 1}, "runtime": {}}');

        self::assertSame(1, $configuration->getSingleBuildValue('$.a'));
    }
}
