<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Commands\Build;

if (PHP_SAPI !== 'cli') {
    echo 'Exiting, not a CLI SAPI';
    exit(0);
}

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

exit((new Build())(array_values($argv)));
