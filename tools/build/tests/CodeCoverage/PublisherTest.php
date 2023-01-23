<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CodeCoverage;

use Bramus\Ansi\Ansi;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Git;
use RuntimeException;

final class PublisherTest extends TestCase
{
    public function testFailsOnWrongArtifactType(): void
    {
        $publisher = new Publisher(new Git(new Ansi()));

        $this->expectException(RuntimeException::class);
        $publisher->publish(new class() implements Artifact {
            public function key(): string
            {
                return 'x';
            }
            public function name(): string
            {
                return 'x';
            }
        });
    }
}
