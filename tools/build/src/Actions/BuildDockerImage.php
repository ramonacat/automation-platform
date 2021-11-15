<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;

final class BuildDockerImage implements BuildAction
{
    private const DEFAULT_TIMEOUT = 3600;

    public function __construct(private string $key, private string $imageName, private string $contextPath = '.', private string $dockerFilePath = 'Dockerfile')
    {
    }

    public function execute(
        ActionOutput $output,
        Context $context
    ): BuildActionResult {
        $process = new InActionProcess(['docker', 'build', '-t', $this->imageName . ':' . $context->buildFacts()->buildId(), '-f', $this->dockerFilePath, $this->contextPath], self::DEFAULT_TIMEOUT);

        return $process->run($output)
            ? BuildActionResult::ok([new ContainerImage($this->key, $this->imageName, $context->buildFacts()->buildId())])
            : BuildActionResult::fail("Failed to build the container image");
    }
}
