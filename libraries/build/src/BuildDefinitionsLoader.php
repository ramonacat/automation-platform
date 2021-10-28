<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

interface BuildDefinitionsLoader
{
    public function target(TargetId $targetId): Target;
}
