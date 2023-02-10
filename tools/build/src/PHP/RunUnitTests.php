<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\CodeCoverage\Artifact;
use Ramona\AutomationPlatformLibBuild\CodeCoverage\Kind;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;

final class RunUnitTests implements BuildAction
{
    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        $coveragePath = $workingDirectory . DIRECTORY_SEPARATOR . '/coverage.xml';

        $process = $context->processBuilder()->build($workingDirectory, ['php', 'vendor/bin/phpunit', '--coverage-clover', $coveragePath], timeout: 600);
        if (!$process->run($output)) {
            return BuildResult::fail('Unit tests failed');
        }

        return BuildResult::ok([
            new Artifact('coverage-unit', $coveragePath, Kind::Clover)
        ]);
    }
}
