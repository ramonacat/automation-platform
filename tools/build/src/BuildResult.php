<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function in_array;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

/**
 * @psalm-immutable
 */
final class BuildResult
{
    /**
     * @param list<Artifact> $artifacts
     */
    private function __construct(private ?string $message, private array $artifacts, private BuildResultWithReason $reason)
    {
    }

    /**
     * @psalm-pure
     * @param list<Artifact> $artifacts
     */
    public static function ok(array $artifacts): self
    {
        return new self(null, $artifacts, BuildResultWithReason::OkBuilt);
    }

    /**
     * @psalm-pure
     * @param list<Artifact> $artifacts
     */
    public static function okCached(array $artifacts): self
    {
        return new self(null, $artifacts, BuildResultWithReason::OkFromCache);
    }

    /**
     * @psalm-pure
     */
    public static function fail(string $message): self
    {
        return new self($message, [], BuildResultWithReason::FailExecutionFailure);
    }

    public static function dependencyFailed(TargetId $dependencyId): self
    {
        return new self("Not executed due to dependency failure: {$dependencyId->toString()}", [], BuildResultWithReason::FailDependencyFailed);
    }

    public function hasSucceeded(): bool
    {
        return in_array($this->reason, [BuildResultWithReason::OkBuilt, BuildResultWithReason::OkFromCache], true);
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * @return list<Artifact>
     */
    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function reason(): BuildResultWithReason
    {
        return $this->reason;
    }
}
