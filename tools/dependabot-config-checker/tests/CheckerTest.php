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

    public function testWillFailIfAnEntryDoesNotMatchAProject(): void
    {
        $output = $this->createMock(CheckerOutput::class);
        $output->expects(self::once())->method('invalid')->with('This "updates" entry does not correspond to a project, directory: /a/b');
        $checker = new Checker([], $output);

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
        $output = $this->createMock(CheckerOutput::class);
        $output
            ->expects(self::once())
            ->method('invalid')
            ->with(
                <<<EOC
                Some "updates" section entries are missing: [
                    "\\/"
                ]
                EOC
            );
        $checker = new Checker(['/', '/a/b'], $output);

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
            'This "updates" entry has a non-int key, received: "a"',
        ];

        yield [
            <<<EOC
            version: 2
            updates: "invalid"
            EOC,
            '"updates" key is not an array',
        ];

        yield [
            <<<EOC
            version: 2
            updates:
                - true
            EOC,
            'This "updates" entry is not an array, received: true'
        ];

        yield [
            <<<EOC
            version: 2
            updates:
                - package-ecosystem: "npm"
            EOC,
            'This "updates" entry does not have a valid "directory" entry, entry index: 0'
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
