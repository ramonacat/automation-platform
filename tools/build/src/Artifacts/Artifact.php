<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

interface Artifact
{
    public function key(): string;
    public function name(): string;
}
