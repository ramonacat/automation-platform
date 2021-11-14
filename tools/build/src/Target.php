<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;

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

    public function execute(ActionOutput $output, Context $context): BuildActionResult
    {
        return $this->action->execute($output, $context);
    }

    /**
     * @return list<TargetId>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }
}
