<?php

declare(strict_types=1);

use Ramona\AutomationPlatformToolDependabotConfigChecker\Checker;
use Ramona\AutomationPlatformToolDependabotConfigChecker\ProjectFinder;
use function Safe\file_get_contents;

require_once __DIR__ . '/../vendor/autoload.php';

$projects = ProjectFinder::find();

$checker = new Checker($projects);

$checker->validate(file_get_contents(__DIR__ . '/../../../.github/dependabot.yaml'));
