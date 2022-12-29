<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Definitions;

use Bramus\Ansi\Ansi;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Definition\InvalidBuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class BuildDefinitionBuilderTest extends TestCase
{
    public function testWillThrowIfNoTargetsAreDefined(): void
    {
        $builder = new BuildDefinitionBuilder(__DIR__, new Ansi());

        $this->expectException(InvalidBuildDefinitionBuilder::class);
        $this->expectExceptionMessage('No build targets were provided');

        $builder->build(
            new BuildFacts('test', false, 1, 2, 'main'),
            Configuration::fromJsonString('{}')
        );
    }

    public function testWillAddAllTargetsFromTheGenerators(): void
    {
        $builder = new BuildDefinitionBuilder(__DIR__, new Ansi());

        $generatorA = $this->createMock(TargetGenerator::class);
        $generatorA->method('targets')->willReturn([
            new Target(new TargetId(__DIR__, 'a'), new NoOp()),
            new Target(new TargetId(__DIR__, 'b'), new NoOp()),
        ]);
        $generatorB = $this->createMock(TargetGenerator::class);
        $generatorB->method('targets')->willReturn([
            new Target(new TargetId(__DIR__, 'c'), new NoOp()),
            new Target(new TargetId(__DIR__, 'd'), new NoOp()),
        ]);

        $builder->addTargetGenerator($generatorA);
        $builder->addTargetGenerator($generatorB);

        $buildDefinition = $builder->build(
            new BuildFacts('test', false, 1, 2, 'main'),
            Configuration::fromJsonString('{}')
        );

        self::assertEquals(['a', 'b', 'c', 'd'], $buildDefinition->targetNames());
    }

    public function testCanAddDefaultTarget(): void
    {
        $builder = new BuildDefinitionBuilder(__DIR__, new Ansi());

        $generatorA = $this->createMock(TargetGenerator::class);
        $generatorA->method('targets')->willReturn([
            new Target(new TargetId(__DIR__, 'a'), new NoOp()),
            new Target(new TargetId(__DIR__, 'b'), new NoOp()),
        ]);
        $generatorA->method('defaultTargetIds')->with(DefaultTargetKind::Build)->willReturn([new TargetId(__DIR__, 'a')]);
        $generatorB = $this->createMock(TargetGenerator::class);
        $generatorB->method('targets')->willReturn([
            new Target(new TargetId(__DIR__, 'c'), new NoOp()),
            new Target(new TargetId(__DIR__, 'd'), new NoOp()),
            new Target(new TargetId(__DIR__, 'e'), new NoOp()),
        ]);
        $generatorB->method('defaultTargetIds')->with(DefaultTargetKind::Build)->willReturn([new TargetId(__DIR__, 'c'), new TargetId(__DIR__, 'e')]);

        $builder->addTargetGenerator($generatorA);
        $builder->addTargetGenerator($generatorB);

        $builder->addDefaultTarget(DefaultTargetKind::Build);

        $buildDefinition = $builder->build(
            new BuildFacts('test', false, 1, 2, 'main'),
            Configuration::fromJsonString('{}')
        );

        self::assertEquals(
            [
                new TargetId(__DIR__, 'a'),
                new TargetId(__DIR__, 'c'),
                new TargetId(__DIR__, 'e'),
            ],
            $buildDefinition->target('build')->dependencies()
        );
    }
}
