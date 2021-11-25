<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use RuntimeException;
use function sprintf;

final class TargetDoesNotExist extends RuntimeException
{
    public function __construct(private TargetId $targetId)
    {
        parent::__construct(sprintf('The target "%s" does not exist', $this->targetId->toString()));
    }

    public function targetId(): TargetId
    {
        return $this->targetId;
    }
}
