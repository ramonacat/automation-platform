<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\Commands\Build;
use Ramona\AutomationPlatformLibBuild\CodeCoverage\Publisher;

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

$options = getopt('', ['environment:'], $restIndex);

if ($options === false) {
    $options = [];
}

$restArguments = array_slice($argv, $restIndex);
exit((new Build())($argv[0], $options, $restArguments));
