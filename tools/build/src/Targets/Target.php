<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;

final class Target
{
    /**
     * @param list<TargetId> $dependencies
     */
    public function __construct(private TargetId $id, private BuildAction $action, private array $dependencies = [])
    {
    }

    public function id(): TargetId
    {
        return $this->id;
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        return $this->action->execute($output, $context, $workingDirectory);
    }

    /**
     * @return list<TargetId>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }
}
