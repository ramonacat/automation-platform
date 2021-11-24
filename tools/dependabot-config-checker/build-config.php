<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\PHP\Configuration;
use Ramona\AutomationPlatformLibBuild\PHP\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;

return static function (BuildDefinitionBuilder $builder) {
    $phpTargetGenerator = new TargetGenerator(__DIR__, new Configuration(minMsi: 52, minCoveredMsi: 65));

    foreach ($phpTargetGenerator->targets() as $target) {
        $builder->addTarget($target);
    }

    $builder->addTarget(new Target('build', new NoOp(), $phpTargetGenerator->buildTargetIds()));
};
