<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use function implode;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;

/**
 * @api
 */
final class RunProcess implements BuildAction
{
    private const DEFAULT_TIMEOUT = 30;

    /**
     * @param list<string> $command
     * @param list<Artifact> $artifacts
     * @param array<string, string> $additionalEnvironmentVariables
     */
    public function __construct(
        private array $command,
        private array $artifacts = [],
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT,
        private array $additionalEnvironmentVariables = []
    ) {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        $process = $context->processBuilder()->build(
            $workingDirectory,
            $this->command,
            $this->timeoutSeconds,
            $this->additionalEnvironmentVariables,
        );

        $commandName = implode(' ', $this->command);

        return $process->run($output)
            ? BuildResult::ok($this->artifacts)
            : BuildResult::fail("Failed to execute command \"{$commandName}\"");
    }
}
