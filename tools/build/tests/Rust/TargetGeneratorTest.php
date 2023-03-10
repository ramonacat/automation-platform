<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Rust;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Rust\DependencyDetector;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;

final class TargetGeneratorTest extends TestCase
{
    public function testGeneratesClippyCheck(): void
    {
        $generator = new TargetGenerator('.', $this->createMock(DependencyDetector::class));
        $targets = $generator->targets(new BuildFacts('asdf', null, 1, 2), Configuration::fromJsonString('{}'));

        self::assertEquals($targets[0]->id()->target(), 'rust-clippy');
    }

    public function testDefaultFixTargetContainsRustFmt(): void
    {
        $generator = new TargetGenerator('.', $this->createMock(DependencyDetector::class));
        $defaultTargets = $generator->defaultTargetIds(DefaultTargetKind::Fix);

        self::assertEquals($defaultTargets[0]->target(), 'rust-fmt');
    }
}
