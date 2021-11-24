<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Target;

return static function (BuildDefinitionBuilder $builder) {
    $builder->addTarget(new Target('a', new NoOp()));
    $builder->addTarget(new Target('b', new NoOp()));
};
