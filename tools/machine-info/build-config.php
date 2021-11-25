<?php

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\Target;

return static function (BuildDefinitionBuilder $builder) {
    $rustTargetGenerator = new TargetGenerator(__DIR__);

    $builder->addTargetGenerator($rustTargetGenerator);

    $builder->addTarget(
        new Target(
            'build',
            new NoOp(),
            $rustTargetGenerator->buildTargetIds()
        )
    );
};