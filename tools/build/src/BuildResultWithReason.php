<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

enum BuildResultWithReason
{
    case FailDependencyFailed;
    case FailExecutionFailure;
    case OkBuilt;
    case OkFromCache;
}
