<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use PHPUnit\Framework\TestCase;

final class TargetDoesNotExistTest extends TestCase
{
    public function testTargetId(): void
    {
        $targetId = new TargetId(__DIR__, 'bar');
        $exception = new TargetDoesNotExist($targetId);

        $this->assertSame($targetId, $exception->targetId());
    }
}
