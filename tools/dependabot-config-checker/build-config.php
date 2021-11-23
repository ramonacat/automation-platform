<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\PHP\Configuration;
use Ramona\AutomationPlatformLibBuild\PHP\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;

$phpTargetGenerator = new TargetGenerator(__DIR__, new Configuration(minMsi: 52, minCoveredMsi: 65));

return new BuildDefinition(
    array_merge(
        $phpTargetGenerator->targets(),
        [
            new Target('build', new NoOp(), $phpTargetGenerator->buildTargetIds()),
        ]
    )
);
