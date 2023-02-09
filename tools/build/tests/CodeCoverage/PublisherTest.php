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

    /**
     * @dataProvider invalidCoverageFileProvider
     */
    public function testInvalidCoverageFile(string $rawFile, string $exceptionMessage): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('readFile')->willReturn($rawFile);

        $publisher = new Publisher(new Git(new Ansi()), $filesystem, new State());

        $this->expectException(InvalidCoverageFile::class);
        $this->expectExceptionMessage($exceptionMessage);
        $publisher->publish(new CodeCoverageArtifact('x', 'y', Kind::LlvmJson));
    }

    /**
     * @return iterable<int, array{0:string, 1:string}>
     */
    private function invalidCoverageFileProvider(): iterable
    {
        yield ['{"data": []}', 'Could not find key "data[0].totals" in file "y"'];
        yield ['{"data": [{"totals": false}]}', 'Key "data[0].totals" in file "y" is not an array'];
        yield ['{"data": [{"totals": {}}]}', 'Could not find key "data[0].totals.lines" in file "y"'];
        yield ['{"data": [{"totals": {"lines": false}}]}', 'Could not find key "data[0].totals.lines" in file "y"'];
    }

    public function testCanPublishLlvmJson(): void
    {
        $state = new State();

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('readFile')->willReturn('{"data": [{"totals": {"lines": {"percent": 0.5}}}]}');

        $publisher = new Publisher(new Git(new Ansi()), $filesystem, $state);
        $publisher->publish(new CodeCoverageArtifact('x', 'y', Kind::LlvmJson));

        self::assertEqualsWithDelta($state->coverages(), ['y' => 0.005], 0.01);
    }

    public function testSetsCoverageToZeroIfThereIsNoKeyForPercent(): void
    {
        $state = new State();

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('readFile')->willReturn('{"data": [{"totals": {"lines": {}}}]}');

        $publisher = new Publisher(new Git(new Ansi()), $filesystem, $state);
        $publisher->publish(new CodeCoverageArtifact('x', 'y', Kind::LlvmJson));

        self::assertEqualsWithDelta($state->coverages(), ['y' => 0.0], 0.01);
    }

    /**
     * @dataProvider invalidCloverXmlFileProvider
     */
    public function testFailsOnInvalidCloverXmlFile(string $contents, string $exceptionMessage): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('readFile')->willReturn($contents);

        $publisher = new Publisher(new Git(new Ansi()), $filesystem, new State());

        $this->expectException(InvalidCoverageFile::class);
        $this->expectExceptionMessage($exceptionMessage);
        $publisher->publish(new CodeCoverageArtifact('x', 'y', Kind::Clover));
    }

    /**
     * @return iterable<int, array{0:string, 1:string}>
     */
    private function invalidCloverXmlFileProvider(): iterable
    {
        yield ['not xml', 'Could not load XML file: y'];
    }
}
