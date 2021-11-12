<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

/**
 * @psalm-immutable
 */
final class BuildActionResult
{
    private function __construct(private bool $succeeded, private ?string $message)
    {
    }

    /**
     * @psalm-pure
     */
    public static function ok(): self
    {
        return new self(true, null);
    }

    /**
     * @psalm-pure
     */
    public static function fail(string $message): self
    {
        return new self(false, $message);
    }

    public function hasSucceeded(): bool
    {
        return $this->succeeded;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
