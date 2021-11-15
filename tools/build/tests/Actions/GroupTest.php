<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;

final class GroupTest extends TestCase
{
    public function testWillExecuteAllTheSubactions(): void
    {
        $a1 = $this->createMock(BuildAction::class);
        $a2 = $this->createMock(BuildAction::class);

        $a1->expects(self::once())->method('execute')->willReturn(BuildActionResult::ok([]));
        $a2->expects(self::once())->method('execute')->willReturn(BuildActionResult::ok([]));

        $group = new Group([$a1, $a2]);

        $group->execute(
            $this->createMock(ActionOutput::class),
            $this->createContext()
        );
    }

    public function testWillCollectAllArtifacts(): void
    {
        $artifact1 = new ContainerImage('ramona-test-1', 'ramona/test1', '1.0');
        $artifact2 = new ContainerImage('ramona-test-1', 'ramona/test2', '0.1');

        $a1 = $this->createMock(BuildAction::class);
        $a2 = $this->createMock(BuildAction::class);

        $a1->method('execute')->willReturn(BuildActionResult::ok([$artifact1]));
        $a2->method('execute')->willReturn(BuildActionResult::ok([$artifact2]));

        $group = new Group([$a1, $a2]);

        $result = $group->execute(
            $this->createMock(ActionOutput::class),
            $this->createContext()
        );

        self::assertEquals([$artifact1, $artifact2], $result->artifacts());
    }

    private function createContext(): Context
    {
        return new Context(
            Configuration::fromJsonString('{}'),
            new Collector(),
            new BuildFacts('test')
        );
    }
}
