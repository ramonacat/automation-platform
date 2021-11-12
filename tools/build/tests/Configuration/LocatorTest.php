<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Configuration;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Configuration\FileNotFound;
use Ramona\AutomationPlatformLibBuild\Configuration\Locator;
use Ramona\AutomationPlatformLibBuild\WorkingDirectory;
use function Safe\realpath;
use function sys_get_temp_dir;

final class LocatorTest extends TestCase
{
    public function testWillLocateTheConfigurationFile(): void
    {
        $locator = new Locator();
        $path = WorkingDirectory::in(__DIR__ . '/locator/test/a/', static function () use ($locator) {
            return $locator->locateConfigurationFile();
        });

        self::assertEquals(realpath(__DIR__ . '/locator/configuration.json'), realpath($path));
    }

    public function testWillThrowIfTheConfigurationFileDoesNotExist(): void
    {
        $locator = new Locator();

        $this->expectException(FileNotFound::class);
        WorkingDirectory::in(sys_get_temp_dir(), static function () use ($locator) {
            $locator->locateConfigurationFile();
        });
    }
}
