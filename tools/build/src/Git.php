<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

interface Git
{
    public function repositoryRoot(): string;
    public function currentCommitHash(): string;
    public function rawDiffTo(string $commitHash): string;
    /**
     * @return list<string>
     */
    public function listUntrackedFiles(): array;
    public function parseRevision(string $revision): string;
    public function readFileAtRef(string $ref, string $path): string;
    /**
     * @return list<string>
     */
    public function listModfiedFiles(string $since): array;
}
