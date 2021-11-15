<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use function Safe\file_get_contents;
use function sys_get_temp_dir;
use function uniqid;

final class PutFileTest extends TestCase
{
    public function testCanPutAFile(): void
    {
        $targetFile = sys_get_temp_dir() . '/' . uniqid('', true);
        $action = new PutFile($targetFile, fn () => 'test');

        $action->execute(
            $this->createMock(ActionOutput::class),
            new Context(Configuration::fromJsonString('{}'), new Collector(), new BuildFacts('test'))
        );

        self::assertSame('test', file_get_contents($targetFile));
    }
}
