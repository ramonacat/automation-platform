<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use function assert;
use function is_string;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;
use function sprintf;

final class KustomizeApply implements BuildAction
{
    public function __construct(private string $overridePath)
    {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        /** @var mixed $context */
        $context = $context->configuration()->getSingleBuildValue('$.kubernetes.context');

        assert(is_string($context));

        $process = new InActionProcess(
            $workingDirectory,
            [
                'kubectl',
                '--context',
                $context,
                'apply',
                '-k',
                $this->overridePath
            ],
            10
        );

        return $process->run($output) ?
            BuildResult::ok([])
            : BuildResult::fail(sprintf('Failed to apply k8s override "%s"', $this->overridePath));
    }
}
