<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Docker;

use function array_merge;
use function array_values;
use function assert;
use function is_array;
use function is_string;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;

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

        // todo make the type check here cleaner and throw exceptions as needed
        $cleanDockerBuildCommand = [];
        assert(is_array($dockerBuildCommand));
        foreach ($dockerBuildCommand as $item) {
            assert(is_string($item));
            $cleanDockerBuildCommand[] = $item;
        }

        $process = new InActionProcess(
            $workingDirectory,
            array_values(array_merge($cleanDockerBuildCommand, ['-t', $this->imageName . ':' . $context->buildFacts()->buildId(), '-f', $this->dockerFilePath, $this->contextPath])),
            self::DEFAULT_TIMEOUT
        );

        return $process->run($output)
            ? BuildResult::ok([new ContainerImage($this->key, $this->imageName, $context->buildFacts()->buildId())])
            : BuildResult::fail("Failed to build the container image");
    }
}
