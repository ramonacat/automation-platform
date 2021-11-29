<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use function Safe\file_get_contents;
use function sys_get_temp_dir;
use function uniqid;

final class PutRuntimeConfigurationTest extends TestCase
{
    public function testCanPutRuntimeConfigurationAtSpecifiedPath(): void
    {
        $tempDirectory = sys_get_temp_dir();
        $filename = uniqid('', true);
        $path = $tempDirectory . '/' . $filename;

        $action = new PutRuntimeConfiguration($filename);

        $action->execute(
            $this->createMock(TargetOutput::class),
            ContextFactory::create(Configuration::fromJsonString('{"runtime": {"a": 1}}')),
            $tempDirectory
        );

        $this->assertJsonStringEqualsJsonString('{"a": 1}', file_get_contents($path));
    }
}
