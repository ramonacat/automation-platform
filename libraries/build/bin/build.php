<?php

use Ramona\AutomationPlatformLibBuild\BuildAction;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

$actionGroups = require 'build-config.php';

if(!is_array($actionGroups) || count($actionGroups) === 0) {
    fprintf(STDERR, 'The build config does not define any action groups.');
    exit(1);
}

$actions = array_keys($actionGroups);
if($argc !== 2) {
    fprintf(STDERR, 'Usage: %s [action-name]', $argv[0]);
    fprintf(STDERR, 'Supported actions: %s', implode(', ', $actions));
    exit(2);
}

if(!isset($actionGroups[$argv[1]])) {
    fprintf(STDERR, 'Invalid action supplied. Supported actions: %s', implode(', ', $actions));
    exit(3);
}

$buildAction = $actionGroups[$argv[1]];
if(!$buildAction instanceof BuildAction) {
    fprintf(STDERR, 'Invalid config, the action for %s is not a build action.', $argv[1]);
    exit(4);
}

$result = $buildAction->execute();
if(!$result->hasSucceeded()) {
    fprintf(STDERR,  'The build has failed.');
    fprintf(STDERR, $result->getMessage());
}