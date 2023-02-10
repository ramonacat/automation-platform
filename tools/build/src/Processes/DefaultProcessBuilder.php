<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Processes;

final class DefaultProcessBuilder implements ProcessBuilder
{
    /**
     * @param list<string> $command
     * @param array<string, string> $additionalEnvironmentVariables
     */
    public function build(
        string $workingDirectory,
        array $command,
        int $timeout,
        array $additionalEnvironmentVariables = []
    ): InActionProcess {
        return new DefaultInActionProcess($workingDirectory, $command, $timeout, $additionalEnvironmentVariables);
    }
}
