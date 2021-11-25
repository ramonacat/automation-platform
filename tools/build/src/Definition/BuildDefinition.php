<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function array_map;
use function array_search;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetDoesNotExist;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use function Safe\getcwd;

final class BuildDefinition
{
    /**
     * @psalm-readonly
     * @var non-empty-list<Target>
     */
    private array $targets;
    private string $path;

    /**
     * @param non-empty-list<Target> $targets
     */
    public function __construct(array $targets)
    {
        $this->targets = $targets;
        $this->path = getcwd();
    }

    /**
     * @return non-empty-list<string>
     */
    public function targetNames(): array
    {
        return array_map(static fn (Target $t) => $t->name(), $this->targets);
    }

    public function target(string $targetNMame): Target
    {
        if (($targetIndex = array_search($targetNMame, $this->targetNames(), true)) === false) {
            throw new TargetDoesNotExist(new TargetId($this->path, $targetNMame));
        }

        return $this->targets[$targetIndex];
    }
}
