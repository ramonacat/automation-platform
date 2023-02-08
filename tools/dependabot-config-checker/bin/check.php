<?php

declare(strict_types=1);

use Ramona\AutomationPlatformToolDependabotConfigChecker\Checker;
use Ramona\AutomationPlatformToolDependabotConfigChecker\DefaultCheckerOutput;
use Ramona\AutomationPlatformToolDependabotConfigChecker\DockerfileFinder;
use Ramona\AutomationPlatformToolDependabotConfigChecker\ProjectFinder;
use function Safe\file_get_contents;

require_once __DIR__ . '/../vendor/autoload.php';

$projects = ProjectFinder::find();
$dockerFileFinder = new DockerfileFinder($projectRoot = realpath(dirname(__DIR__, 3)) . DIRECTORY_SEPARATOR);
$dockerFileDirectories = $dockerFileFinder->find();

$checker = new Checker(array_merge(['/'], $projects, $dockerFileDirectories), new DefaultCheckerOutput());

exit($checker->validate(file_get_contents(__DIR__ . '/../../../.github/dependabot.yaml')));
