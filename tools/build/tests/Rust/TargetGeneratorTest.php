<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Rust;

use Bramus\Ansi\Ansi;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;

final class TargetGeneratorTest extends TestCase
{
    public function testGeneratesClippyCheck(): void
    {
        $generator = new TargetGenerator('.', new Ansi());
        $targets = $generator->targets(new BuildFacts('asdf', null, 1, 2), Configuration::fromJsonString('{}'));

        self::assertEquals($targets[0]->id()->target(), 'rust-clippy');
    }
}
