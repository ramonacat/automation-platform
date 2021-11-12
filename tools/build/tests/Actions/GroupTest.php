<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

final class GroupTest extends TestCase
{
    public function testWillExecuteAllTheSubactions()
    {
        $a1 = $this->createMock(BuildAction::class);
        $a2 = $this->createMock(BuildAction::class);

        $a1->expects(self::once())->method('execute')->willReturn(BuildActionResult::ok());
        $a2->expects(self::once())->method('execute')->willReturn(BuildActionResult::ok());

        $group = new Group([$a1, $a2]);

        $group->execute($this->createMock(ActionOutput::class), Configuration::fromJsonString('{}'));
    }
}
