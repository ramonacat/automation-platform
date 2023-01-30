<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Artifacts;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Artifacts\UnexpectedArtifactType;

final class MockArtifactA implements Artifact
{
    public function key(): string
    {
        return 'a';
    }
    
    public function name(): string
    {
        return 'a';
    }
}

final class MockArtifactB implements Artifact
{
    public function key(): string
    {
        return 'b';
    }
    
    public function name(): string
    {
        return 'b';
    }
}

final class UnexpectedArtifactTypeTest extends TestCase
{
    public function testFromArtifact(): void
    {
        $exception = UnexpectedArtifactType::fromArtifact(MockArtifactB::class, new MockArtifactA());

        self::assertSame('Expected an artifact of type Tests\Ramona\AutomationPlatformLibBuild\Artifacts\MockArtifactB, got Tests\Ramona\AutomationPlatformLibBuild\Artifacts\MockArtifactA', $exception->getMessage());
    }
}
