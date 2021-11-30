<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class BuildActionResultTest extends TestCase
{
    public function testOkWillReturnASuccessfulResult(): void
    {
        $result = BuildResult::ok([]);

        self::assertTrue($result->hasSucceeded());
    }

    public function testFailWillReturnAFailedResult(): void
    {
        $result = BuildResult::fail('x');

        self::assertFalse($result->hasSucceeded());
    }

    public function testFailWillRetainAMessage(): void
    {
        $result = BuildResult::fail('x');

        self::assertSame('x', $result->message());
    }

    public function testDependencyFailedWillReturnAFailedResult(): void
    {
        $result = BuildResult::dependencyFailed(new TargetId(__DIR__, 'a'));

        self::assertFalse($result->hasSucceeded());
    }

    public function testDependencyFailedWillHaveAnErrorMessage(): void
    {
        $targetId = new TargetId(__DIR__, 'a');
        $result = BuildResult::dependencyFailed($targetId);

        self::assertSame('Not executed due to dependency failure: ' . $targetId->toString(), $result->message());
    }
}
