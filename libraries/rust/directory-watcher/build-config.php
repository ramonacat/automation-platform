<?php

use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;

return static function (BuildDefinitionBuilder $builder) {
    $builder->addRustTargetGenerator();

    $builder->addDefaultTarget(DefaultTargetKind::Build);
    $builder->addDefaultTarget(DefaultTargetKind::Fix);
};
