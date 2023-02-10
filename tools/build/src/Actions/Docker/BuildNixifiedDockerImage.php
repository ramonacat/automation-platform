<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Docker;

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;

final class BuildNixifiedDockerImage implements BuildAction
{
    private const DEFAULT_TIMEOUT = 3600;

    public function __construct(
        private string $key,
        private string $imageName,
        private string $contextPath = '.',
        private string $nixFilePath = './docker/docker.nix'
    ) {
    }

    public function execute(
        TargetOutput $output,
        Context $context,
        string $workingDirectory
    ): BuildResult {
        $process = $context->processBuilder()->build(
            $workingDirectory,
            // TODO: pass the image name as an argument
            [
                'sh',
                '-c',
                'crate2nix generate && $(nix-build --no-out-link ' . $this->nixFilePath . ' --argstr tag \"' . $context->buildFacts()->buildId() . '\") | docker load'
            ],
            self::DEFAULT_TIMEOUT
        );

        if (!$process->run($output)) {
            return BuildResult::fail("Failed to build the container image");
        }

        return BuildResult::ok($this->createArtifacts($context));
    }

    /**
     * @return list<Artifact>
     */
    private function createArtifacts(Context $context): array
    {
        return [
            new ContainerImage(
                $this->key,
                $this->imageName,
                $context->buildFacts()->buildId()
            )
        ];
    }
}
