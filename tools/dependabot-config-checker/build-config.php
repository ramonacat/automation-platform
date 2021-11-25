<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\PHP\Configuration;
use Ramona\AutomationPlatformLibBuild\PHP\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\Target;

return static function (BuildDefinitionBuilder $builder) {
    $phpTargetGenerator = new TargetGenerator(__DIR__, new Configuration(minMsi: 52, minCoveredMsi: 65));

    $builder->addTargetGenerator($phpTargetGenerator);

    $builder->addTarget(new Target('build', new NoOp(), $phpTargetGenerator->buildTargetIds()));
};
