<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\Writers\StreamWriter;
use function file_exists;
use function fprintf;
use function implode;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use const PHP_EOL;
use const PHP_SAPI;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Configuration\Locator;
use function Safe\getcwd;
use function Safe\realpath;
use const STDERR;

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

$workingDirectory = realpath(getcwd());
$configuration = (new Locator())->locateConfigurationFile();

$logger = new Logger('ap-build');
$logger->pushHandler(new StreamHandler($workingDirectory . '/build.log'));
$ansi = new Ansi(new StreamWriter('php://stdout'));

$buildDefinitions = new DefaultBuildDefinitionsLoader();
$executor = new BuildExecutor(
    $logger,
    new StyledBuildOutput($ansi),
    $buildDefinitions,
    Configuration::fromFile($configuration)
);

if ($argc !== 2) {
    fprintf(STDERR, 'Usage: %s [action-name]%s', $argv[0], PHP_EOL);
    fprintf(STDERR, 'Supported actions: %s%s', implode(', ', $buildDefinitions->getActionNames($workingDirectory)), PHP_EOL);
    exit(2);
}

try {
    $result = $executor->executeTarget(getcwd(), $argv[1]);
} catch (TargetDoesNotExist $exception) {
    fprintf(STDERR, 'The action "%s" does not exist', $exception->actionName());
    exit(3);
}

if (!$result->hasSucceeded()) {
    fprintf(STDERR, 'The build has failed.' . PHP_EOL);
    fprintf(STDERR, $result->getMessage() ?? '<no message>');
    exit(4);
}
