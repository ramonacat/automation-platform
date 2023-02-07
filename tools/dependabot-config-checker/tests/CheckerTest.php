<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformToolDependabotConfigChecker;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformToolDependabotConfigChecker\Checker;
use Ramona\AutomationPlatformToolDependabotConfigChecker\CheckerOutput;

final class CheckerTest extends TestCase
{
    public function testWillFailOnMissingUpdatesSection(): void
    {
        $checker = new Checker([], $this->createMock(CheckerOutput::class));

        self::assertSame(1, $checker->validate('version: 2'));
    }

    public function testWillFailIfUpdatesEntryIsNotAnArray(): void
    {
        $checker = new Checker([], $this->createMock(CheckerOutput::class));

        $result = $checker->validate(
            <<<EOC
            version: 2
            updates:
                - true
            EOC
        );
        self::assertSame(1, $result);
    }

    public function testWillFailIfUpdatesHasANonIntKey(): void
    {
        $checker = new Checker([], $this->createMock(CheckerOutput::class));

        $result = $checker->validate(
            <<<EOC
            version: 2
            updates: { gemuese: true }
            EOC
        );
        self::assertSame(1, $result);
    }

    public function testWillFailIfUpdatesEntryIsMissingDirectory(): void
    {
        $checker = new Checker([], $this->createMock(CheckerOutput::class));

        $result = $checker->validate(
            <<<EOC
            version: 2
            updates:
                - package-ecosystem: "npm"
            EOC
        );
        self::assertSame(1, $result);
    }

    public function testWillFailIfAnEntryDoesNotMatchAProject(): void
    {
        $checker = new Checker([], $this->createMock(CheckerOutput::class));

        $result = $checker->validate(
            <<<EOC
            version: 2
            updates:
                - package-ecosystem: "npm"
                  directory: "/a/b"
            EOC
        );

        self::assertSame(1, $result);
    }

    public function testWillFailIfAProjectDoesNotHaveAnEntry(): void
    {
        $checker = new Checker(['/', '/a/b'], $this->createMock(CheckerOutput::class));

        $result = $checker->validate(
            <<<EOC
            version: 2
            updates:
                - package-ecosystem: "npm"
                  directory: "/a/b"
            EOC
        );

        self::assertSame(1, $result);
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testWillFailForInvalidConfig(string $config, string $errorMessage): void
    {
        $allOutputs = [];
        $output = $this->createMock(CheckerOutput::class);
        $output->method('invalid')->willReturnCallback(function (string $message) use (&$allOutputs) {
            $allOutputs[] = $message;
        });
        $checker = new Checker(['/', '/a/b'], $output);

        $result = $checker->validate($config);

        self::assertSame(1, $result);
        self::assertContains($errorMessage, $allOutputs);
    }

    public function invalidConfigProvider(): iterable
    {
        yield [
            <<<'EOC'
            version: 2
            updates: {"a": []}
            EOC,
            'This "updates" entry has a non-int key, received: a',
        ];

        yield [
            <<<EOC
            version: 2
            updates: "invalid"
            EOC,
            '"updates" key is not an array',
        ];
    }

    public function testWillPassForAValidConfig(): void
    {
        $checker = new Checker(['/a/b'], $this->createMock(CheckerOutput::class));

        $result = $checker->validate(
            <<<EOC
            version: 2
            updates:
                - package-ecosystem: "npm"
                  directory: "/a/b"
            EOC
        );

        self::assertSame(0, $result);
    }
}
