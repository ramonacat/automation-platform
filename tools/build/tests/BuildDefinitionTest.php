<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetDoesNotExist;
use function Safe\getcwd;
use function sprintf;

final class BuildDefinitionTest extends TestCase
{
    public function testCanGetTargetNames(): void
    {
        $definition = new BuildDefinition([
            new Target('t1', new NoOp()),
            new Target('t2', new NoOp()),
            new Target('t3', new NoOp()),
        ]);

        self::assertSame(['t1', 't2', 't3'], $definition->targetNames());
    }

    public function testCanGetTargetByName(): void
    {
        $target2 = new Target('t2', new NoOp());
        $definition = new BuildDefinition([
            new Target('t1', new NoOp()),
            $target2,
        ]);

        self::assertSame($target2, $definition->target('t2'));
    }

    public function testGettingTargetThrowsIfATargetDoesNotExist(): void
    {
        $definition = new BuildDefinition([
            new Target('t1', new NoOp()),
            new Target('t2', new NoOp()),
            new Target('t3', new NoOp()),
        ]);

        $cwd = getcwd();
        $this->expectExceptionMessage(sprintf('The target "%s:t4" does not exist', $cwd));
        $this->expectException(TargetDoesNotExist::class);
        $definition->target('t4');
    }
}
