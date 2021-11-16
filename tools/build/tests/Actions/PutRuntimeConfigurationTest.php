<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
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
            ContextFactory::create(Configuration::fromJsonString('{"runtime": {"a": 1}}'))
        );

        $this->assertJsonStringEqualsJsonString('{"a": 1}', file_get_contents($path));
    }
}
