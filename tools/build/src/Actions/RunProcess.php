<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use function implode;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;

/**
 * @api
 */
final class RunProcess implements BuildAction
{
    private const DEFAULT_TIMEOUT = 30;

    /**
     * @param list<string> $command
     * @param list<Artifact> $artifacts
     */
    public function __construct(
        private array $command,
        private array $artifacts = [],
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT
    ) {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        $process = new InActionProcess($workingDirectory, $this->command, $this->timeoutSeconds);

        $commandName = implode(' ', $this->command);
        return $process->run($output)
            ? BuildResult::ok($this->artifacts)
            : BuildResult::fail("Failed to execute command \"{$commandName}\"");
    }
}
