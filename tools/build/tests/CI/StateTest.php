<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\CI;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\CI\State;

final class StateTest extends TestCase
{
    public function testHasActor(): void
    {
        $state = new State('actor', 'base-ref', 'current-ref');

        self::assertSame('actor', $state->actor());
    }

    public function testHasBaseRef(): void
    {
        $state = new State('actor', 'base-ref', 'current-ref');

        self::assertSame('base-ref', $state->baseRef());
    }
}
