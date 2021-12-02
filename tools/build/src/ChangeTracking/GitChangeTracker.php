<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\ChangeTracking;

use function array_map;
use const DIRECTORY_SEPARATOR;
use Exception;
use function explode;
use Psr\Log\LoggerInterface;
use function Safe\realpath;
use function sha1;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;
use Symfony\Component\Process\Process;
use function trim;

final class GitChangeTracker implements ChangeTracker
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getCurrentStateId(): string
    {
        $currentCommitHash = $this->currentCommitHash();
        $rawDiff = $this->rawDiffTo($currentCommitHash);

        if ($rawDiff !== '') {
            return $currentCommitHash . '-' . sha1($rawDiff);
        }

        return $currentCommitHash;
    }

    public function wasModifiedSince(string $previousStateId, string $directory): bool
    {
        if ($this->getCurrentStateId() === $previousStateId) {
            return false;
        }

        $separatorIndex = strpos($previousStateId, '-');
        if ($separatorIndex !== false) {
            $previousCommitHash = substr($previousStateId, 0, $separatorIndex);
        } else {
            $previousCommitHash = $previousStateId;
        }

        $directory = str_replace(realpath($this->repositoryRoot()), '', realpath($directory));
        // skip the leading slash
        $directory = substr($directory, 1);
        // git uses `/` in its output, regardless of the OS
        $directory = str_replace(DIRECTORY_SEPARATOR, '/', $directory);

        $modifiedFiles = ['git', 'diff', '--name-only', $previousCommitHash];
        $process = new Process($modifiedFiles);
        try {
            $process->mustRun();
        } catch (Exception $e) {
            $this->logger->error('Failed to get list of modified files', ['previous-commit-hash' => $previousCommitHash, 'exception' => $e]);
            return true;
        }

        $output = explode("\n", trim($process->getOutput()));
        $changedFiles = array_map(static fn (string $line) => substr($line, 0), $output);

        foreach ($changedFiles as $changedFile) {
            if (str_starts_with($changedFile, $directory)) {
                return true;
            }
        }

        return false;
    }

    private function currentCommitHash(): string
    {
        $currentCommit = ['git', 'rev-parse', 'HEAD'];
        $process = new Process($currentCommit);
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function rawDiffTo(string $commitHash): string
    {
        $process = new Process(['git', 'diff', $commitHash]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function repositoryRoot(): string
    {
        $process = new Process(['git', 'rev-parse', '--show-toplevel']);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
