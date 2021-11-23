<?php

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;

$rustTargetGenerator = new TargetGenerator(__DIR__);

return new BuildDefinition(
    array_merge(
        [
            new Target(
                'build',
                new NoOp(),
                $rustTargetGenerator->buildTargetIds()
            ),
        ],
        $rustTargetGenerator->targets()
    )
);