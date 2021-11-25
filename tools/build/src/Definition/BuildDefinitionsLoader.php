<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

interface BuildDefinitionsLoader
{
    public function target(TargetId $targetId): Target;

    /**
     * @return non-empty-list<string>
     */
    public function getActionNames(string $workingDirectory): array;
}
