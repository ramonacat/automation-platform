<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function file_exists;
use function fprintf;
use function implode;
use const PHP_EOL;
use function Safe\getcwd;
use function Safe\realpath;
use const STDERR;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

$workingDirectory = realpath(getcwd());

$buildDefinitions = new DefaultBuildDefinitionsLoader();
$executor = new BuildExecutor($buildDefinitions);

if ($argc !== 2) {
    fprintf(STDERR, 'Usage: %s [action-name]%s', $argv[0], PHP_EOL);
    fprintf(STDERR, 'Supported actions: %s', implode(', ', $buildDefinitions->getActionNames($workingDirectory)));
    exit(2);
}

try {
    $result = $executor->executeTarget(getcwd(), $argv[1]);
} catch (ActionDoesNotExist $exception) {
    fprintf(STDERR, 'The action "%s" does not exist', $exception->actionName());
    exit(3);
}

if (!$result->hasSucceeded()) {
    fprintf(STDERR, 'The build has failed.');
    fprintf(STDERR, $result->getMessage() ?? '<no message>');
    exit(4);
}
