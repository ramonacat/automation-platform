<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

enum BuildFailReason
{
    case DependencyFailed;
    case ExecutionFailure;
}
