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

    /**
     * @param callable(string):void $onOutputLine
     * @param callable(string):void $onErrorLine
     */
    public function execute(callable $onOutputLine, callable $onErrorLine): BuildActionResult
    {
        return $this->action->execute($onOutputLine, $onErrorLine);
    }

    /**
     * @return list<TargetId>
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
