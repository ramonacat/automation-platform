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

    public function testCanGetSingleValue(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a" : 1}, "runtime": {}}');

        self::assertSame(1, $configuration->getSingleBuildValue('$.a'));
    }

    public function testWillThrowOnMissingBuildKey(): void
    {
        $configuration = Configuration::fromJsonString('{}');

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('The "build" key is missing');
        $configuration->getSingleBuildValue('$.a');
    }

    public function testWillThrowIfValueIsMissing(): void
    {
        $configuration = Configuration::fromFile(__DIR__ . '/test.config.json');

        $this->expectException(ConfigurationValueNotFound::class);
        $configuration->getSingleBuildValue('$.doesnotexist');
    }

    public function testWillGetTheValueFromTheLastOverride(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a": 1}}');
        $configuration = $configuration->merge(Configuration::fromJsonString('{"build": {"a": 2}}'));

        self::assertSame(2, $configuration->getSingleBuildValue('$.a'));
    }

    public function testWillGetTheValueFromTheLastOverrideForNested(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a": {"b": 1}}}');
        $configuration = $configuration->merge(Configuration::fromJsonString('{"build": {"a": {"b": 2}}}'));

        self::assertSame(2, $configuration->getSingleBuildValue('$.a.b'));
    }

    public function testCanGetNestedOverrideValue(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a": {"x": 1}}}');
        $configuration = $configuration->merge(Configuration::fromJsonString('{"build": {"a": {"x": 7}}}'));

        self::assertSame(7, $configuration->getSingleBuildValue('$.a.x'));
    }

    public function testCanOverrideNestedRuntimeConfiguration(): void
    {
        $configuration = Configuration::fromJsonString('{"runtime": {"a": {"x": 1, "y": 2}}}');
        $configuration = $configuration->merge(Configuration::fromJsonString('{"runtime": {"a": {"y": 12}}}'));

        self::assertSame(['a' => ['x' => 1, 'y' => 12]], $configuration->getRuntimeConfiguration());
    }

    public function testGetMultipleValuesInRuntimeConfiguration(): void
    {
        $configuration = Configuration::fromJsonString('{"runtime": {"x": 1, "y": 2}}');

        self::assertSame(['x' => 1, 'y' => 2], $configuration->getRuntimeConfiguration());
    }

    public function testMergeDoesNotMutateTheOriginalConfiguration(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a": 1}}');
        $configuration->merge(Configuration::fromJsonString('{"build": {"a": 2}}'));

        self::assertSame(1, $configuration->getSingleBuildValue('$.a'));
    }

    public function testAllowsOverridesWithoutTheBuildKey(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a": 1}}');
        $configuration = $configuration->merge(Configuration::fromJsonString('{}'));

        self::assertSame(1, $configuration->getSingleBuildValue('$.a'));
    }

    public function testAllowsGettingValuesFromTheOriginalConfigurationWhenThereIsAnOverride(): void
    {
        $configuration = Configuration::fromJsonString('{"build": {"a": 1, "b": 3}}');
        $configuration = $configuration->merge(Configuration::fromJsonString('{"build": {"a": 2}}'));

        self::assertSame(3, $configuration->getSingleBuildValue('$.b'));
    }

    public function testAllowsOverridesWithoutTheRuntimeKey(): void
    {
        $configuration = Configuration::fromJsonString('{"runtime": {"a": 1}}');
        $configuration = $configuration->merge(Configuration::fromJsonString("{}"));
        $configuration = $configuration->merge(Configuration::fromJsonString('{"runtime": {"a": 2}}'));

        self::assertSame(['a' => 2], $configuration->getRuntimeConfiguration());
    }

    public function testWillKeepNonOverridenRuntimeKeys(): void
    {
        $configuration = Configuration::fromJsonString('{"runtime": {"a": 1, "b": 3}}');
        $configuration = $configuration->merge(Configuration::fromJsonString("{}"));
        $configuration = $configuration->merge(Configuration::fromJsonString('{"runtime": {"a": 2}}'));

        self::assertSame(['a' => 2, 'b' => 3], $configuration->getRuntimeConfiguration());
    }

    public function testThrowsIfTheConfigurationIsNotAnArray(): void
    {
        $this->expectException(InvalidConfiguration::class);
        Configuration::fromJsonString('123');
    }

    public function testThrowsIfBuildSectionIsNotAnArray(): void
    {
        $configuration = Configuration::fromJsonString('{"build": 123}');
        $this->expectException(InvalidConfiguration::class);

        $configuration->getSingleBuildValue('$.a');
    }

    public function testThrowsIfRuntimeIsNotAnArray(): void
    {
        $configuration = Configuration::fromJsonString('{"runtime": 12}');
        $this->expectException(InvalidConfiguration::class);

        $configuration->getRuntimeConfiguration();
    }
}
