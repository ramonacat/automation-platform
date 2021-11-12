<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Configuration;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Configuration\ConfigurationValueNotFound;
use Ramona\AutomationPlatformLibBuild\Configuration\InvalidConfiguration;

final class ConfigurationTest extends TestCase
{
    public function testCanLoadFromFile(): void
    {
        $configuration = Configuration::fromFile(__DIR__ . '/test.config.json');

        self::assertEquals(1, $configuration->getSingleBuildValue('$.a'));
    }

    public function testWillThrowOnMissingBuildKey(): void
    {
        $configuration = Configuration::fromJsonString('{}');

        $this->expectException(InvalidConfiguration::class);
        $configuration->getSingleBuildValue('$.a');
    }

    public function testWillThrowIfValueIsMissing(): void
    {
        $configuration = Configuration::fromFile(__DIR__ . '/test.config.json');

        $this->expectException(ConfigurationValueNotFound::class);
        $configuration->getSingleBuildValue('$.doesnotexist');
    }

    public function testWillThrowOnMissingRuntimeKey(): void
    {
        $configuration = Configuration::fromJsonString('{}');


        $this->expectException(InvalidConfiguration::class);
        $configuration->getRuntimeConfiguration();
    }
}
