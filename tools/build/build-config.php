<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\PHP\Configuration;
use Ramona\AutomationPlatformLibBuild\PHP\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;

return static function (BuildDefinitionBuilder $builder) {
    $builder->addTargetGenerator(
        new TargetGenerator(
            __DIR__,
            new Configuration(
                minMsi: 96,
                minCoveredMsi: 99
            )
        )
    );

    $builder->addDefaultTarget(DefaultTargetKind::Build);
    $builder->addDefaultTarget(DefaultTargetKind::Fix);
};
