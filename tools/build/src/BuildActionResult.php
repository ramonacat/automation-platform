<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

/**
 * @psalm-immutable
 */
final class BuildActionResult
{
    /**
     * @param list<Artifact> $artifacts
     */
    private function __construct(private bool $succeeded, private ?string $message, private array $artifacts)
    {
    }

    /**
     * @psalm-pure
     * @param list<Artifact> $artifacts
     */
    public static function ok(array $artifacts): self
    {
        return new self(true, null, $artifacts);
    }

    /**
     * @psalm-pure
     */
    public static function fail(string $message): self
    {
        return new self(false, $message, []);
    }

    public static function dependencyFailed(TargetId $dependencyId): self
    {
        return new self(false, "Not executed due to dependency failure: {$dependencyId->toString()}", []);
    }

    public function hasSucceeded(): bool
    {
        return $this->succeeded;
    }

    public function getMessage(): ?string
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
}
