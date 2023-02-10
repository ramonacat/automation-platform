<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Processes;

use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;

interface InActionProcess
{
    public function run(TargetOutput $output, string $standardIn = ''): bool;
}
