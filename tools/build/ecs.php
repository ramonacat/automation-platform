<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__ . '/vendor/ramona/automation-platform-lib-coding-standard/ecs.php');

    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::PATHS, [
        'src',
        'tests',
        'bin/build.php',
        'build-config.php',
        'ecs.php',
    ]);
};
