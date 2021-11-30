<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;

final class GroupTest extends TestCase
{
    public function testWillExecuteAllTheSubactions(): void
    {
        $a1 = $this->createMock(BuildAction::class);
        $a2 = $this->createMock(BuildAction::class);

        $a1->expects(self::once())->method('execute')->willReturn(BuildResult::ok([]));
        $a2->expects(self::once())->method('execute')->willReturn(BuildResult::ok([]));

        $group = new Group([$a1, $a2]);

        $group->execute(
            $this->createMock(TargetOutput::class),
            $this->createContext(),
            __DIR__
        );
    }

    public function testWillCollectAllArtifacts(): void
    {
        $artifact1 = new ContainerImage('ramona-test-1', 'ramona/test1', '1.0');
        $artifact2 = new ContainerImage('ramona-test-1', 'ramona/test2', '0.1');

        $a1 = $this->createMock(BuildAction::class);
        $a2 = $this->createMock(BuildAction::class);

        $a1->method('execute')->willReturn(BuildResult::ok([$artifact1]));
        $a2->method('execute')->willReturn(BuildResult::ok([$artifact2]));

        $group = new Group([$a1, $a2]);

        $result = $group->execute(
            $this->createMock(TargetOutput::class),
            $this->createContext(),
            __DIR__
        );

        self::assertEquals([$artifact1, $artifact2], $result->artifacts());
    }

    private function createContext(): Context
    {
        return ContextFactory::create();
    }
}
