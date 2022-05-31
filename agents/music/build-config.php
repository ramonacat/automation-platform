<?php

use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;

return static function (BuildDefinitionBuilder $builder) {
    $builder->addTargetGenerator(new TargetGenerator(__DIR__));
    $builder->addDefaultTarget(DefaultTargetKind::Build);
    $builder->addDefaultTarget(DefaultTargetKind::Fix);
};