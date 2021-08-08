<?php

use Ramona\AutomationPlatformLibBuild\ActionGroup;
use Ramona\AutomationPlatformLibBuild\RunProcess;

return [
    'check' => new ActionGroup([
        new RunProcess('php vendor/bin/ecs'),
        new RunProcess('php vendor/bin/psalm'),
    ]),
    'cs-fix' => new RunProcess('php vendor/bin/ecs --fix')
];