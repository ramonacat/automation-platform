<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;
use function count;

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
     * @var list<Dependency>
     */
    private array $dependencies;

    /**
     * @param list<Dependency> $dependencies
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

    public function execute(): BuildActionResult
    {
        return $this->action->execute();
    }

    /**
     * @return list<Dependency>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function hasDependencies(): bool
    {
        return count($this->dependencies) > 0;
    }
}
