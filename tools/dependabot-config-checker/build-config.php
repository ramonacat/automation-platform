<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\PHP\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;

$phpTargetGenerator = new TargetGenerator(__DIR__, 53, 66);

return new BuildDefinition(
    array_merge(
        $phpTargetGenerator->targets(),
        [
            new Target('build', new NoOp(), $phpTargetGenerator->targetIds()),
        ]
    )
);
