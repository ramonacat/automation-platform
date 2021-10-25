<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\ActionGroup;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Dependency;
use Ramona\AutomationPlatformLibBuild\RunProcess;
use Ramona\AutomationPlatformLibBuild\Target;

return new BuildDefinition([
    new Target('coding-standard', new RunProcess('php vendor/bin/ecs')),
    new Target('type-check', new RunProcess('php vendor/bin/psalm')),
    new Target(
        'check',
        new ActionGroup([]),
        [
            new Dependency(__DIR__, 'coding-standard'),
            new Dependency(__DIR__, 'type-check')
        ]
    ),
    new Target('cs-fix', new RunProcess('php vendor/bin/ecs --fix')),
]);
