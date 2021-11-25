<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Artifacts;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\ArtifactKeyAlreadyUsed;
use Ramona\AutomationPlatformLibBuild\Artifacts\ArtifactNotFound;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class CollectorTest extends TestCase
{
    public function testWillThrowIfTheSameArtifactKeyAppearsTwice(): void
    {
        $collector = new Collector();

        $targetId = new TargetId('.', 'a');
        $artifact = new ContainerImage('a', 'b', '1');

        $collector->collect($targetId, $artifact);

        $this->expectException(ArtifactKeyAlreadyUsed::class);
        $this->expectExceptionMessage("The artifact key \"a\" was already used in the build-config in: \"{$targetId->path()}\"");
        $collector->collect($targetId, $artifact);
    }

    public function testCanGetArtifactByKey(): void
    {
        $collector = new Collector();

        $targetId = new TargetId('.', 'a');
        $artifact = new ContainerImage('a', 'b', '1');

        $collector->collect($targetId, $artifact);

        self::assertSame($artifact, $collector->getByKey($targetId->path(), 'a'));
    }

    public function testWillThrowWhenTryingToGetAnArtifactThatDoesNotExist(): void
    {
        $collector = new Collector();

        $targetId = new TargetId('.', 'a');
        $artifact = new ContainerImage('a', 'b', '1');

        $collector->collect($targetId, $artifact);

        $this->expectException(ArtifactNotFound::class);
        $this->expectExceptionMessage("Artifact \"b\" not found in \"{$targetId->path()}\"");
        $collector->getByKey($targetId->path(), 'b');
    }
}
