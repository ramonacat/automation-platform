<?php

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;

return static function (BuildDefinitionBuilder $builder) {
    $rustTargetGenerator = new TargetGenerator(__DIR__);

    foreach ($rustTargetGenerator->targets() as $target) {
        $builder->addTarget($target);
    }

    $builder->addTarget(
        new Target(
            'build',
            new NoOp(),
            $rustTargetGenerator->buildTargetIds()
        )
    );
};