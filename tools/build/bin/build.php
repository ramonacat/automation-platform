<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\Writers\StreamWriter;
use function file_exists;
use function fprintf;
use function getenv;
use function implode;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use const PHP_EOL;
use const PHP_SAPI;
use Ramona\AutomationPlatformLibBuild\BuildOutput\CIBuildOutput;
use Ramona\AutomationPlatformLibBuild\BuildOutput\StyledBuildOutput;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Configuration\Locator;
use Ramona\AutomationPlatformLibBuild\Log\LogFormatter;
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

$isCi = getenv('CI') !== false;

$workingDirectory = realpath(getcwd());
$configuration = (new Locator())->locateConfigurationFile();

$logger = new Logger('ap-build');
$logHandler = new StreamHandler($workingDirectory . '/build.log');
$logHandler->setFormatter(new LogFormatter());
$logger->pushHandler($logHandler);
$ansi = new Ansi(new StreamWriter('php://stdout'));

$buildDefinitions = new DefaultBuildDefinitionsLoader();
$executor = new BuildExecutor(
    $logger,
    $isCi ? new CIBuildOutput($ansi) : new StyledBuildOutput($ansi),
    $buildDefinitions,
    Configuration::fromFile($configuration)
);

if ($argc !== 2) {
    fprintf(STDERR, 'Usage: %s [action-name]%s', $argv[0], PHP_EOL);
    fprintf(STDERR, 'Supported actions: %s%s', implode(', ', $buildDefinitions->getActionNames($workingDirectory)), PHP_EOL);
    exit(2);
}

try {
    $result = $executor->executeTarget(new TargetId(getcwd(), $argv[1]));
} catch (TargetDoesNotExist $exception) {
    fprintf(STDERR, 'The target "%s" does not exist', $exception->targetId()->toString());
    exit(3);
}

if (!$result->hasSucceeded()) {
    fprintf(STDERR, 'The build has failed.' . PHP_EOL);
    fprintf(STDERR, $result->getMessage() ?? '<no message>');
    exit(4);
}
