<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use RuntimeException;
use function sprintf;

final class ActionDoesNotExist extends RuntimeException
{
    private string $actionName;

    public function __construct(string $actionName)
    {
        parent::__construct(sprintf('The action "%s" does not exist', $actionName));
        $this->actionName = $actionName;
    }

    public function actionName(): string
    {
        return $this->actionName;
    }
}
