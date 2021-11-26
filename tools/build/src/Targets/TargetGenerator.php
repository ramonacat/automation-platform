<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

interface TargetGenerator
{
    /**
     * @return non-empty-list<Target>
     */
    public function targets(BuildFacts $facts, Configuration $configuration): array;

    /**
     * @return list<TargetId>
     */
    public function defaultTargetIds(DefaultTargetKind $kind): array;
}
