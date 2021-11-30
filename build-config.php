<?php

use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

return static function (BuildDefinitionBuilder $builder) {
    $services = glob(__DIR__ . '/services/*');
    $libraries = glob(__DIR__ . '/libraries/*/*');
    $tools = glob(__DIR__ . '/tools/*');

    $builder->addTarget(
        'build',
        new NoOp(),
        array_map(
            static fn(string $path) => new TargetId($path, 'build'),
            array_merge($tools, $libraries, $services)
        )
    );

    $builder->addTarget(
        'fix',
        new NoOp(),
        array_map(
            static fn(string $path) => new TargetId($path, 'fix'),
            array_merge($tools, $libraries, $services)
        )
    );

    $builder->addTarget(
        'deploy',
        new NoOp(),
        array_merge(
            [new TargetId(__DIR__, 'build')],
            array_map(
                static fn(string $path) => new TargetId($path, 'deploy'),
                $services
            )
        )
    );
};