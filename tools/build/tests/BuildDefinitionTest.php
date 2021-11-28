<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Definition\DuplicateTarget;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetDoesNotExist;
use function sprintf;

final class BuildDefinitionTest extends TestCase
{
    public function testCanGetTargetNames(): void
    {
        $definition = new BuildDefinition(__DIR__, [
            new Target('t1', new NoOp()),
            new Target('t2', new NoOp()),
            new Target('t3', new NoOp()),
        ]);

        self::assertSame(['t1', 't2', 't3'], $definition->targetNames());
    }

    public function testCanGetTargetByName(): void
    {
        $target2 = new Target('t2', new NoOp());
        $definition = new BuildDefinition(__DIR__, [
            new Target('t1', new NoOp()),
            $target2,
        ]);

        self::assertSame($target2, $definition->target('t2'));
    }

    public function testGettingTargetThrowsIfATargetDoesNotExist(): void
    {
        $definition = new BuildDefinition(__DIR__, [
            new Target('t1', new NoOp()),
            new Target('t2', new NoOp()),
            new Target('t3', new NoOp()),
        ]);

        $this->expectExceptionMessage(sprintf('The target "%s:t4" does not exist', __DIR__));
        $this->expectException(TargetDoesNotExist::class);
        $definition->target('t4');
    }

    public function testWillThrowOnDuplicateTargetNames(): void
    {
        $this->expectException(DuplicateTarget::class);
        $this->expectExceptionMessage('Encountered a duplicate target: ' . __DIR__ . ':a');

        new BuildDefinition(__DIR__, [
            new Target('a', new NoOp()),
            new Target('a', new NoOp()),
        ]);
    }
}
