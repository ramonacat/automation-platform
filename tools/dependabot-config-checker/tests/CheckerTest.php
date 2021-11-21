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

    public function testWillFailIfUpdatesSectionIsNotAnArray(): void
    {
        $checker = new Checker([], $this->createMock(CheckerOutput::class));

        $result = $checker->validate(
            <<<EOC
            version: 2
            updates: "invalid"
            EOC
        );
        self::assertSame(1, $result);
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
