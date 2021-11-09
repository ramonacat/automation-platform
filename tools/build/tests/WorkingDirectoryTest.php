<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\WorkingDirectory;
use function Safe\getcwd;
use function Safe\realpath;

final class WorkingDirectoryTest extends TestCase
{
    public function testWillExecuteInTheGivenDirectory(): void
    {
        $targetDirectory = realpath(__DIR__ . '/a');

        WorkingDirectory::in($targetDirectory, fn () => self::assertEquals($targetDirectory, realpath(getcwd())));
    }

    public function testWillReturnToTheOriginalDirectory(): void
    {
        $currentWorkingDirectory = getcwd();

        WorkingDirectory::in(__DIR__ . '/a', fn () => null);

        self::assertEquals(getcwd(), $currentWorkingDirectory);
    }
}
