<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\CodeCoverage;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\CodeCoverage\Artifact;
use Ramona\AutomationPlatformLibBuild\CodeCoverage\Kind;

final class ArtifactTest extends TestCase
{
    public function testArtifactHasKind(): void
    {
        $artifact = new Artifact('key', 'path', Kind::Clover);

        self::assertSame(Kind::Clover, $artifact->kind());
    }
}
