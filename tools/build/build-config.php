<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\PHP\Configuration;
use Ramona\AutomationPlatformLibBuild\PHP\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;

return static function (BuildDefinitionBuilder $definitionBuilder) {
    $phpTargetGenerator = new TargetGenerator(__DIR__, new Configuration(minMsi: 94, minCoveredMsi: 98));

    foreach ($phpTargetGenerator->targets() as $target) {
        $definitionBuilder->addTarget($target);
    }

    $definitionBuilder->addTarget(new Target('build', new NoOp(), $phpTargetGenerator->buildTargetIds()));
};
