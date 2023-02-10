<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions\Docker;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\Docker\BuildNixifiedDockerImage;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;
use Ramona\AutomationPlatformLibBuild\Processes\ProcessBuilder;

final class BuildNixifiedDockerImageTest extends TestCase
{
    public function testBuildsImage(): void
    {
        $processBuilder = $this->createMock(ProcessBuilder::class);
        $process = $this->createMock(InActionProcess::class);

        $process
            ->method('run')
            ->willReturn(true);

        $processBuilder
            ->expects(self::once())
            ->method('build')
            ->with(
                '.',
                [
                    'sh',
                    '-c',
                    'crate2nix generate && $(nix-build --no-out-link ./docker/docker.nix --argstr tag \"test\") | docker load'
                ],
                3600
            )
            ->willReturn($process);

        $context = new Context(
            Configuration::fromJsonString('{}'),
            new Collector(),
            new BuildFacts('test', null, 1, 1),
            $processBuilder
        );
        $action = new BuildNixifiedDockerImage(
            'a',
            'test',
            't',
        );

        $result = $action->execute($this->createMock(TargetOutput::class), $context, '.');

        self::assertTrue($result->hasSucceeded());
        self::assertEquals([
            new ContainerImage('a', 'test', 'test')
        ], $result->artifacts());
    }
}
