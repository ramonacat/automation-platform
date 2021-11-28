<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Docker;

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;
use function Safe\file_get_contents;

final class LintDockerfile implements BuildAction
{
    public function __construct(private string $dockerfilePath)
    {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildActionResult
    {
        $process = new InActionProcess($workingDirectory, ['docker', 'run', '--rm', '-i', 'hadolint/hadolint'], 30);
        return $process->run($output, file_get_contents($this->dockerfilePath))
            ? BuildActionResult::ok([])
            : BuildActionResult::fail('Dockerfile linting failed');
    }
}
