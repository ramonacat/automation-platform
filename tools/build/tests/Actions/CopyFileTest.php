<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use const DIRECTORY_SEPARATOR;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\CopyFile;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use function Safe\touch;
use function sys_get_temp_dir;
use function uniqid;

final class CopyFileTest extends TestCase
{
    public function testCanCopyAFile(): void
    {
        $tempdir = sys_get_temp_dir();

        $sourceFilename = uniqid('', true);
        $targetFilename = uniqid('', true);

        $sourcePath = $tempdir . DIRECTORY_SEPARATOR . $sourceFilename;

        touch($sourcePath);

        $targetPath = $tempdir . DIRECTORY_SEPARATOR . $targetFilename;

        $action = new CopyFile($sourceFilename, $targetFilename);
        $action->execute(
            $this->createMock(TargetOutput::class),
            new Context(Configuration::fromJsonString('{}'), new Collector(), new BuildFacts('test', false, 1, 1, 'main')),
            $tempdir
        );

        self::assertFileExists($targetPath);
    }
}
