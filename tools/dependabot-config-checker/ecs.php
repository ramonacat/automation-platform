<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->import(__DIR__ . '/vendor/ramona/automation-platform-lib-coding-standard/ecs.php');

    $config->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/bin',
        __DIR__ . '/build-config.php',
        __DIR__ . '/ecs.php',
    ]);
};
