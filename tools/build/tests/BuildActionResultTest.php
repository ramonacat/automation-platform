<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class BuildActionResultTest extends TestCase
{
    public function testOkWillReturnASuccessfulResult(): void
    {
        $result = BuildActionResult::ok([]);

        self::assertTrue($result->hasSucceeded());
    }

    public function testFailWillReturnAFailedResult(): void
    {
        $result = BuildActionResult::fail('x');

        self::assertFalse($result->hasSucceeded());
    }

    public function testFailWillRetainAMessage(): void
    {
        $result = BuildActionResult::fail('x');

        self::assertSame('x', $result->getMessage());
    }

    public function testDependencyFailedWillReturnAFailedResult(): void
    {
        $result = BuildActionResult::dependencyFailed(new TargetId(__DIR__, 'a'));

        self::assertFalse($result->hasSucceeded());
    }

    public function testDependencyFailedWillHaveAnErrorMessage(): void
    {
        $targetId = new TargetId(__DIR__, 'a');
        $result = BuildActionResult::dependencyFailed($targetId);

        self::assertSame('Not executed due to dependency failure: ' . $targetId->toString(), $result->getMessage());
    }
}
