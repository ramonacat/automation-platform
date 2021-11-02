<?php

use Ramona\AutomationPlatformLibBuild\Actions\ActionGroup;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

$tag = str_replace('.', '', uniqid('', true));

return new BuildDefinition([
    new Target('build-dev', new NoOp(), [new TargetId(__DIR__, 'check')]),
    new Target('check', new RunProcess('cargo clippy')),
]);