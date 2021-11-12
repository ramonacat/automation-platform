<?php

use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

return new BuildDefinition([
    new Target(
        'build-dev',
        new NoOp(),
        [
            new TargetId(__DIR__, 'clippy'),
            new TargetId(__DIR__, 'fmt'),
            new TargetId(__DIR__, 'tests-unit'),
        ]
    ),
    new Target('clippy', new RunProcess('cargo clippy')),
    new Target('fmt', new RunProcess('cargo fmt -- --check')),
    new Target('tests-unit', new RunProcess('cargo test')),
]);