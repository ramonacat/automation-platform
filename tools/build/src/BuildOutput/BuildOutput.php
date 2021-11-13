<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\TargetId;

interface BuildOutput extends ActionOutput
{
    public function setTargetCount(int $count): void;
    public function startTarget(TargetId $id): void;
    public function getCollectedStandardOutput(): string;
    public function getCollectedStandardError(): string;
    public function finalizeTarget(TargetId $targetId, BuildActionResult $result): void;
}
