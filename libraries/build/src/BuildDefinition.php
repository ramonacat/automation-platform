<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function array_map;
use function array_search;

final class BuildDefinition
{
    /**
     * @psalm-readonly
     * @var non-empty-list<Target>
     */
    private array $targets;

    /**
     * @param non-empty-list<Target> $targets
     */
    public function __construct(array $targets)
    {
        $this->targets = $targets;
    }

    /**
     * @return non-empty-list<string>
     */
    public function actionNames(): array
    {
        return array_map(static fn (Target $t) => $t->name(), $this->targets);
    }

    public function target(string $actionName): Target
    {
        if (($targetIndex = array_search($actionName, $this->actionNames(), true)) === false) {
            throw new ActionDoesNotExist($actionName);
        }

        return $this->targets[$targetIndex];
    }
}
