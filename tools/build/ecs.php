<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->import(__DIR__ . '/vendor/ramona/automation-platform-lib-coding-standard/ecs.php');

    $config->paths([
        'src',
        'tests',
        'bin/build.php',
        'build-config.php',
        'ecs.php',
    ]);
};
