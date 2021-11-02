<?php

use Ramona\AutomationPlatformLibBuild\Actions\ActionGroup;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

// todo tools should also be included here
$services = glob(__DIR__.'/services/*');
$libraries = glob(__DIR__.'/libraries/*/*');

return new BuildDefinition([
    new Target(
        'build-dev',
        new NoOp(),
        array_map(
            fn($path) => new TargetId($path, 'build-dev'),
            array_merge($libraries, $services)
        )

    ),
    new Target(
        'deploy-dev',
        new NoOp(),
        array_merge(
            [new TargetId(__DIR__, 'build-dev')],
            array_map(
                fn($path) => new TargetId($path, 'deploy-dev'),
                $services
            )
        )
    ),
]);