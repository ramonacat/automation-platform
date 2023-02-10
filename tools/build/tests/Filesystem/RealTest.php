<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Filesystem;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Filesystem\Real;

final class RealTest extends TestCase
{
    public function testCanReadFile(): void
    {
        $filesystem = new Real();

        self::assertEquals(
            "Hello, world!\n",
            $filesystem->readFile(__DIR__ . '/fixtures/hello-world.txt')
        );
    }
}
