<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\ChangeTracking;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramona\AutomationPlatformLibBuild\ChangeTracking\GitChangeTracker;
use Ramona\AutomationPlatformLibBuild\Filesystem\Filesystem;
use Ramona\AutomationPlatformLibBuild\Git;

final class GitChangeTrackerTest extends TestCase
{
    public function testCanGetCurrentStateIdForCleanRepository(): void
    {
        $git = $this->createMock(Git::class);
        $git
            ->method('currentCommitHash')
            ->willReturn('123');

        $git
            ->method('rawDiffTo')
            ->willReturn('');

        $git
            ->method('listUntrackedFiles')
            ->willReturn([]);

        $changeTracker = new GitChangeTracker(new NullLogger(), $git, $this->createMock(Filesystem::class));

        $id = $changeTracker->getCurrentStateId();

        self::assertSame('123', $id);
    }

    public function testCanGetCurrentStateIdForRepositoryWithChangedFiles(): void
    {
        $git = $this->createMock(Git::class);
        $git
            ->method('currentCommitHash')
            ->willReturn('123');

        $git
            ->method('rawDiffTo')
            ->willReturn('diff');

        $git
            ->method('listUntrackedFiles')
            ->willReturn([]);

        $changeTracker = new GitChangeTracker(new NullLogger(), $git, $this->createMock(Filesystem::class));

        $id = $changeTracker->getCurrentStateId();

        self::assertSame('123-75a0ee1ba911f2f5199177dfd31808a12511bbdc', $id);
    }

    public function testCanGetCurrentStateIdForRepositoryWithUntrackedFiles(): void
    {
        $git = $this->createMock(Git::class);
        $git
            ->method('currentCommitHash')
            ->willReturn('123');

        $git
            ->method('rawDiffTo')
            ->willReturn('');

        $git
            ->method('listUntrackedFiles')
            ->willReturn(['untracked.txt', 'untracked2.txt']);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('readFile')
            ->willReturnArgument(0);

        $changeTracker = new GitChangeTracker(new NullLogger(), $git, $filesystem);

        $id = $changeTracker->getCurrentStateId();

        self::assertSame('123-b0b629aad777b6b044201e5429669fa2d27f93a4', $id);
    }

    public function testWasModifiedSinceWillReturnFalseIfNothingWasChanged(): void
    {
        $git = $this->createMock(Git::class);
        $git
            ->method('currentCommitHash')
            ->willReturn('123');

        $git
            ->method('rawDiffTo')
            ->willReturn('');

        $git
            ->method('listUntrackedFiles')
            ->willReturn([]);

        $changeTracker = new GitChangeTracker(new NullLogger(), $git, $this->createMock(Filesystem::class));

        self::assertFalse($changeTracker->wasModifiedSince('123', ''));
    }

    public function testWasModifiedSinceOnADirtyRepoWillCheckLatestCommit(): void
    {
        $git = $this->createMock(Git::class);
        $git
            ->method('currentCommitHash')
            ->willReturn('123');

        $git
            ->method('rawDiffTo')
            ->willReturn('');

        $git
            ->method('listUntrackedFiles')
            ->willReturn([]);

        $changeTracker = new GitChangeTracker(new NullLogger(), $git, $this->createMock(Filesystem::class));

        self::assertFalse($changeTracker->wasModifiedSince('123-da39a3ee5e6b4b0d3255bfef95601890afd80709', ''));
    }

    public function testWasModifiedSinceWillReturnTrueIfCurrentCommitIsAfter(): void
    {
        $git = $this->createMock(Git::class);
        $git
            ->method('currentCommitHash')
            ->willReturn('456');

        $git
            ->method('rawDiffTo')
            ->willReturn('');

        $git
            ->method('listUntrackedFiles')
            ->willReturn([]);

        $git
            ->method('listModfiedFiles')
            ->with('123')
            ->willReturn(['a']);

        $filesystem = $this->createMock(Filesystem::class);

        $changeTracker = new GitChangeTracker(new NullLogger(), $git, $filesystem);

        self::assertTrue($changeTracker->wasModifiedSince('123', 'a'));
    }

    public function testWasModifiedSinceWillReturnTrueIfCurrentCommitIsAfterOnDirtyRepo(): void
    {
        $git = $this->createMock(Git::class);
        $git
            ->method('currentCommitHash')
            ->willReturn('456');

        $git
            ->method('rawDiffTo')
            ->willReturn('');

        $git
            ->method('listUntrackedFiles')
            ->willReturn([]);

        $git
            ->method('listModfiedFiles')
            ->with('123')
            ->willReturn(['a']);

        $filesystem = $this->createMock(Filesystem::class);

        $changeTracker = new GitChangeTracker(new NullLogger(), $git, $filesystem);

        self::assertTrue($changeTracker->wasModifiedSince('123-3333', 'a'));
    }
}
