<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\ChangeTracking;

interface ChangeTracker
{
    public function getCurrentStateId(): string;
    public function wasModifiedSince(string $previousStateId, string $directory): bool;
}
