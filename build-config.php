<?php

use Ramona\AutomationPlatformLibBuild\Actions\ActionGroup;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

return static function (BuildDefinitionBuilder $builder) {
    $services = glob(__DIR__ . '/services/*');
    $libraries = glob(__DIR__ . '/libraries/*/*');
    $tools = glob(__DIR__ . '/tools/*');

    $builder->addTarget(
        new Target(
            'build',
            new NoOp(),
            array_map(
                fn($path) => new TargetId($path, 'build'),
                array_merge($tools, $libraries, $services)
            )
        )
    );

    $builder->addTarget(
        new Target(
            'deploy',
            new NoOp(),
            array_merge(
                [new TargetId(__DIR__, 'build')],
                array_map(
                    fn($path) => new TargetId($path, 'deploy'),
                    $services
                )
            )
        )
    );
};