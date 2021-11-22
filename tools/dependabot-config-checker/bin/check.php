<?php

declare(strict_types=1);

use Ramona\AutomationPlatformToolDependabotConfigChecker\Checker;
use Ramona\AutomationPlatformToolDependabotConfigChecker\DefaultCheckerOutput;
use Ramona\AutomationPlatformToolDependabotConfigChecker\DockerfileFinder;
use Ramona\AutomationPlatformToolDependabotConfigChecker\ProjectFinder;
use function Safe\file_get_contents;

require_once __DIR__ . '/../vendor/autoload.php';

$dockerFiles = DockerfileFinder::find();
$projects = ProjectFinder::find();

$checker = new Checker(array_merge(['/'], $projects, $dockerFiles), new DefaultCheckerOutput());

exit($checker->validate(file_get_contents(__DIR__ . '/../../../.github/dependabot.yaml')));
