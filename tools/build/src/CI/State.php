<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CI;

use function getenv;
use RuntimeException;

final class State
{
    public function __construct(
        private string $actor,
        private string $baseRef,
    ) {
    }

    public static function fromEnvironment(): ?self
    {
        if (getenv('CI') === false) {
            return null;
        }

        $actor = getenv('GITHUB_ACTOR');

        if ($actor === false) {
            throw new RuntimeException('GITHUB_ACTOR environment variable is not set');
        }

        $baseRef = getenv('GITHUB_BASE_REF');
        if ($baseRef === false || $baseRef === '') {
            throw new RuntimeException('GITHUB_BASE_REF environment variable is not set');
        }

        return new self($actor, 'origin/' . $baseRef);
    }

    public function actor(): string
    {
        return $this->actor;
    }

    public function baseRef(): string
    {
        return $this->baseRef;
    }
}
