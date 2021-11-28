<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Context;

final class Target
{
    /**
     * @psalm-readonly
     */
    private string $name;

    /**
     * @psalm-readonly
     */
    private BuildAction $action;

    /**
     * @psalm-readonly
     * @var list<TargetId>
     */
    private array $dependencies;

    /**
     * @param list<TargetId> $dependencies
     */
    public function __construct(string $name, BuildAction $action, array $dependencies = [])
    {
        $this->name = $name;
        $this->action = $action;
        $this->dependencies = $dependencies;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildActionResult
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
