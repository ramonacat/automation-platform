<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

enum DefaultTargetKind
{
    case Build;
    case Fix;

    public function targetName(): string
    {
        return match ($this) {
            self::Build => 'build',
            self::Fix => 'fix',
        };
    }
}
