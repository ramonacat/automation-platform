<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CodeCoverage;

use Bramus\Ansi\Ansi;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Artifacts\UnexpectedArtifactType;
use Ramona\AutomationPlatformLibBuild\CodeCoverage\Artifact as CodeCoverageArtifact;
use Ramona\AutomationPlatformLibBuild\Filesystem\Filesystem;
use Ramona\AutomationPlatformLibBuild\Git;

final class PublisherTest extends TestCase
{
    public function testFailsOnWrongArtifactType(): void
    {
        $publisher = new Publisher(new Git(new Ansi()), $this->createMock(Filesystem::class), new State());

        $this->expectException(UnexpectedArtifactType::class);
        
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

    public function testFailsWhenFileCannotBeDecoded(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('readFile')->willReturn('x');

        $publisher = new Publisher(new Git(new Ansi()), $filesystem, new State());

        $this->expectException(InvalidCoverageFile::class);
        $this->expectExceptionMessage('Could not decode file "y"');
        $publisher->publish(new CodeCoverageArtifact('x', 'y', Kind::LlvmJson));
    }

    public function testFailsWhenTotalsAreNotSet(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('readFile')->willReturn('{"data": []}');

        $publisher = new Publisher(new Git(new Ansi()), $filesystem, new State());

        $this->expectException(InvalidCoverageFile::class);
        $publisher->publish(new CodeCoverageArtifact('x', 'y', Kind::LlvmJson));
    }
}
