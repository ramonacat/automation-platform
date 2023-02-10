<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

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
        $coverageFile = $workingDirectory . '/tests_coverage.json';

        $process = $context->processBuilder()->build(
            $workingDirectory,
            ['cargo', 'llvm-cov', '--json', '--output-path', $coverageFile],
            600
        );

        if (!$process->run($output)) {
            return BuildResult::fail('Failed to execute cargo llvm-cov');
        }

        return BuildResult::ok([
            new Artifact(
                'coverage-unit',
                $coverageFile,
                Kind::LlvmJson
            )
        ]);
    }
}
