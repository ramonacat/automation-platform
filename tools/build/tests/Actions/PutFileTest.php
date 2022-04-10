<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\PutFiles;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use function Safe\file_get_contents;
use function sys_get_temp_dir;
use function uniqid;

final class PutFileTest extends TestCase
{
    public function testCanPutAFile(): void
    {
        $tempDirectory = sys_get_temp_dir();
        $targetFilename = uniqid('', true);
        $targetFile = $tempDirectory . '/' . $targetFilename;
        $action = new PutFiles(fn () => [$targetFilename => 'test']);

        $action->execute(
            $this->createMock(TargetOutput::class),
            ContextFactory::create(),
            $tempDirectory
        );

        self::assertSame('test', file_get_contents($targetFile));
    }
}
