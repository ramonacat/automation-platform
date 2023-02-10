<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Docker;

use function array_merge;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Webmozart\Assert\Assert;

final class BuildDockerImage implements BuildAction
{
    private const DEFAULT_TIMEOUT = 3600;

    public function __construct(private string $key, private string $imageName, private string $contextPath = '.', private string $dockerFilePath = 'Dockerfile')
    {
    }

    public function execute(
        TargetOutput $output,
        Context $context,
        string $workingDirectory
    ): BuildResult {
        $dockerBuildCommand = $context->configuration()->getSingleBuildValue('$.docker.build-command');

        Assert::isList($dockerBuildCommand);
        Assert::allString($dockerBuildCommand);

        $process = $context->processBuilder()->build(
            $workingDirectory,
            $this->createDockerBuildCommand($dockerBuildCommand, $context),
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

    /**
     * @param list<string> $dockerBuildCommand
     * @return list<string>
     */
    private function createDockerBuildCommand(array $dockerBuildCommand, Context $context): array
    {
        return array_merge(
            $dockerBuildCommand,
            ['-t', $this->imageName . ':' . $context->buildFacts()->buildId(), '-f', $this->dockerFilePath, $this->contextPath]
        );
    }
}
