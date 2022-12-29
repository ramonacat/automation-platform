<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use Bramus\Ansi\Ansi;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Definition\DefaultBuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\InvalidBuildDefinition;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class DefaultBuildDefinitionsLoaderTest extends TestCase
{
    public function testCanGetADefinitionFromDirectory(): void
    {
        $loader = $this->createLoader();

        $actionNames = $loader->targetNames(__DIR__ . '/test-project/');

        self::assertEquals(['a', 'b'], $actionNames);
    }

    public function testWillThrowOnInvalidBuildDefinition(): void
    {
        $loader = $this->createLoader();

        $this->expectException(InvalidBuildDefinition::class);
        $loader->targetNames(__DIR__ . '/test-invalid-project-1');
    }

    public function testCanGetTargetById(): void
    {
        $loader = $this->createLoader();

        $targetId = new TargetId(__DIR__ . '/test-project/', 'a');
        $target = $loader->target($targetId);

        self::assertEquals(new Target($targetId, new NoOp()), $target);
    }

    private function createLoader(): DefaultBuildDefinitionsLoader
    {
        return new DefaultBuildDefinitionsLoader(
            new BuildFacts('test', false, 1, 1, 'main'),
            Configuration::fromJsonString('{}'),
            new Ansi()
        );
    }
}
