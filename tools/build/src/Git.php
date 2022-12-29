<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Bramus\Ansi\Ansi;
use Symfony\Component\Process\Process;
use function trim;

// TODO This class should not expose methods to run git commands directly, but provide a higher-level API
final class Git
{
    public function __construct(private readonly Ansi $ansi)
    {
    }

    /**
     * @param list<string> $command
     * @return string
     */
    public function runGit(array $command): string
    {
        $process = new Process($command);
        $process->mustRun();

        return trim($process->getOutput());
    }

    public function repositoryRoot(): string
    {
        return $this->runGit(['git', 'rev-parse', '--show-toplevel']);
    }
}
