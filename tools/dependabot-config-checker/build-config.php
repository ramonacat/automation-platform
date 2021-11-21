<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

return new BuildDefinition([
    new Target('coding-standard', new RunProcess(['php', 'vendor/bin/ecs'])),
    new Target('type-check', new RunProcess(['php', 'vendor/bin/psalm'])),
    new Target('tests-unit', new RunProcess(['php', 'vendor/bin/phpunit'])),
    // todo set the number of parallel runs dynamically, once it's supported in build
    new Target('tests-mutation', new RunProcess(['php', 'vendor/bin/infection', '-j6', '--min-msi=52', '--min-covered-msi=60'], timeoutSeconds: 120)),
    new Target('check-transitive-deps', new RunProcess(['composer-require-checker', 'check', 'composer.json'])),
    new Target('check-unused-deps', new RunProcess(['composer', 'unused'])),
    new Target(
        'check',
        new NoOp(),
        [
            new TargetId(__DIR__, 'coding-standard'),
            new TargetId(__DIR__, 'type-check'),
            new TargetId(__DIR__, 'tests-unit'),
            new TargetId(__DIR__, 'tests-mutation'),
            new TargetId(__DIR__, 'check-transitive-deps'),
            new TargetId(__DIR__, 'check-unused-deps'),
        ]
    ),
    new Target('cs-fix', new RunProcess(['php', 'vendor/bin/ecs', '--fix'])),
    new Target('build', new NoOp(), [new TargetId(__DIR__, 'check')]),
]);
