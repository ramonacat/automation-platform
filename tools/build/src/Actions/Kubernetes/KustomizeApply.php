<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Kubernetes;

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Webmozart\Assert\Assert;

final class KustomizeApply implements BuildAction
{
    public function __construct(private string $overridePath)
    {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        $kubernetesContext = $context->configuration()->getSingleBuildValue('$.kubernetes.context');

        Assert::string($kubernetesContext);

        $process = $context->processBuilder()->build(
            $workingDirectory,
            [
                'kubectl',
                '--context',
                $kubernetesContext,
                'apply',
                '-k',
                $this->overridePath
            ],
            30
        );

        return $process->run($output)
            ? BuildResult::ok([])
            : BuildResult::fail("Failed to apply k8s override \"{$this->overridePath}\"");
    }
}
