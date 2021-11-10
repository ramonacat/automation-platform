<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\DefaultBuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\InvalidBuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

final class DefaultBuildDefinitionsLoaderTest extends TestCase
{
    public function testCanGetADefinitionFromDirectory()
    {
        $loader = new DefaultBuildDefinitionsLoader();

        $actionNames = $loader->getActionNames(__DIR__ . '/test-project/');

        self::assertEquals(['a', 'b'], $actionNames);
    }

    public function testWillThrowOnInvalidBuildDefinition()
    {
        $loader = new DefaultBuildDefinitionsLoader();

        $this->expectException(InvalidBuildDefinition::class);
        $loader->getActionNames(__DIR__ . '/test-invalid-project-1');
    }

    public function testCanGetTargetById()
    {
        $loader = new DefaultBuildDefinitionsLoader();

        $actionNames = $loader->target(new TargetId(__DIR__ . '/test-project/', 'a'));

        self::assertEquals(new Target('a', new NoOp()), $actionNames);
    }
}
