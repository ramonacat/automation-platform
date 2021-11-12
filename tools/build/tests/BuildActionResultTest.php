<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;

final class BuildActionResultTest extends TestCase
{
    public function testOkWillReturnASuccessfulResult(): void
    {
        $result = BuildActionResult::ok();

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
}
