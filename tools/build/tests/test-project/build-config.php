<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;

return static function (BuildDefinitionBuilder $builder) {
    $builder->addTarget('a', new NoOp());
    $builder->addTarget('b', new NoOp());
};
