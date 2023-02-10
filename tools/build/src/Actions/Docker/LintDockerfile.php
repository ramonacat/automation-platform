<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Docker;

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use function Safe\file_get_contents;

final class LintDockerfile implements BuildAction
{
    public function __construct(private string $dockerfilePath)
    {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        $process = $context->processBuilder()->build($workingDirectory, ['docker', 'run', '--rm', '-i', 'hadolint/hadolint'], 30);

        return $process->run($output, file_get_contents($this->dockerfilePath))
            ? BuildResult::ok([])
            : BuildResult::fail('Dockerfile linting failed');
    }
}
