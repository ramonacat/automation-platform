<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use function Safe\file_get_contents;
use function sys_get_temp_dir;
use function uniqid;

final class PutRuntimeConfigurationTest extends TestCase
{
    public function testCanPutRuntimeConfigurationAtSpecifiedPath(): void
    {
        $path = sys_get_temp_dir() . '/' . uniqid('', true);

        $action = new PutRuntimeConfiguration($path);

        $action->execute(
            $this->createMock(ActionOutput::class),
            new Context(Configuration::fromJsonString('{"runtime":{"a": 1}}'), new Collector(), new BuildFacts('test'))
        );

        $this->assertJsonStringEqualsJsonString('{"a": 1}', file_get_contents($path));
    }
}
