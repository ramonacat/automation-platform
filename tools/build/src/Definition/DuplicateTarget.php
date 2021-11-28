<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use RuntimeException;
use function sprintf;

final class DuplicateTarget extends RuntimeException
{
    public function __construct(TargetId $id)
    {
        parent::__construct(sprintf('Encountered a duplicate target: %s', $id->toString()));
    }
}
