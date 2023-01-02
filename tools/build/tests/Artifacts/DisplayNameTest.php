<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Artifacts;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\DisplayName;

final class DisplayNameTest extends TestCase
{
    public function testHasDisplayName(): void
    {
        $displayName = new DisplayName('test');

        self::assertSame('test', $displayName->name());
    }
}
