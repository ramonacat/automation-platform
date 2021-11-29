<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Targets;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Targets\FailedToParseTargetId;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class TargetIdTest extends TestCase
{
    public function testWillThrowIfThereIsNoColon(): void
    {
        $this->expectException(FailedToParseTargetId::class);
        $this->expectExceptionMessage('Failed to parse "asdf" as a target id');

        TargetId::fromString('asdf');
    }

    public function testCanParseAValidTargetId(): void
    {
        self::assertEquals(new TargetId(__DIR__, 'a'), TargetId::fromString(__DIR__ . ':a'));
    }
}
