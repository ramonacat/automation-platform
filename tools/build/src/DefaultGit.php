<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function array_filter;
use function array_values;
use Bramus\Ansi\Ansi;
use function explode;
use Symfony\Component\Process\Process;
use function trim;

final class DefaultGit implements Git
{
    public function __construct(private readonly Ansi $ansi)
    {
    }

    /**
     * @param list<string> $command
     * @return string
     */
    private function runGit(array $command): string
    {
        $process = new Process($command);
        $process->mustRun();

        return trim($process->getOutput());
    }

    public function repositoryRoot(): string
    {
        return $this->runGit(['git', 'rev-parse', '--show-toplevel']);
    }

    public function currentCommitHash(): string
    {
        $currentCommit = ['git', 'rev-parse', 'HEAD'];
        return $this->runGit($currentCommit);
    }

    public function rawDiffTo(string $commitHash): string
    {
        return $this->runGit(['git', 'diff', $commitHash]);
    }

    /**
     * @return list<string>
     */
    public function listUntrackedFiles(): array
    {
        $output = $this->runGit(['git', 'ls-files', '--others', '--exclude-standard']);

        return array_values(
            array_filter(
                explode(
                    "\n",
                    trim($output)
                ),
                fn (string $path) => $path !== ''
            )
        );
    }

    public function parseRevision(string $revision): string
    {
        $output = $this->runGit(['git', 'rev-parse', $revision]);

        return trim($output);
    }

    public function readFileAtRef(string $ref, string $path): string
    {
        $output = $this->runGit(['git', 'show', $ref . ':' . $path]);

        return trim($output);
    }

    /**
     * @return list<string>
     */
    public function listModfiedFiles(string $since): array
    {
        $output = $this->runGit(['git', 'diff', '--name-only', $since]);

        return array_values(
            array_filter(
                explode(
                    "\n",
                    trim($output)
                ),
                fn (string $path) => $path !== ''
            )
        );
    }
}
