<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;

return new BuildDefinition(
    [
        new Target('build', new NoOp()),
    ]
);
