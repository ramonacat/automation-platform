<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Actions\CopyFile;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use function Safe\touch;
use function sys_get_temp_dir;
use function uniqid;

final class CopyFileTest extends TestCase
{
    public function testCanCopyAFile(): void
    {
        $tempdir = sys_get_temp_dir();
        $sourcePath = $tempdir . '/' . uniqid('', true);
        touch($sourcePath);
        $targetPath = $tempdir . '/' . uniqid('', true);

        $action = new CopyFile($sourcePath, $targetPath);
        $action->execute($this->createMock(ActionOutput::class), Configuration::fromJsonString('{}'));

        self::assertFileExists($targetPath);
    }
}
