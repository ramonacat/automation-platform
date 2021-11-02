<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

return new BuildDefinition([
    new Target('coding-standard', new RunProcess('php vendor/bin/ecs')),
    new Target('type-check', new RunProcess('php vendor/bin/psalm')),
    new Target('tests-unit', new RunProcess('php vendor/bin/phpunit')),
    new Target(
        'check',
        new NoOp(),
        [
            new TargetId(__DIR__, 'coding-standard'),
            new TargetId(__DIR__, 'type-check'),
            new TargetId(__DIR__, 'tests-unit'),
        ]
    ),
    new Target('cs-fix', new RunProcess('php vendor/bin/ecs --fix')),
]);
