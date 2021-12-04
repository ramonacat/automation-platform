<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function array_map;
use function array_search;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetDoesNotExist;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

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
    public function __construct(string $path, array $targets)
    {
        $namesSeen = [];
        foreach ($targets as $target) {
            if (isset($namesSeen[$target->id()->toString()])) {
                throw new DuplicateTarget($target->id());
            }

            $namesSeen[$target->id()->toString()] = true;
        }

        $this->targets = $targets;
        $this->path = $path;
    }

    /**
     * @return non-empty-list<string>
     */
    public function targetNames(): array
    {
        return array_map(
            static fn (Target $t) => $t->id()->target(),
            $this->targets
        );
    }

    public function target(string $targetName): Target
    {
        if (($targetIndex = array_search($targetName, $this->targetNames(), true)) === false) {
            throw new TargetDoesNotExist(new TargetId($this->path, $targetName));
        }

        return $this->targets[$targetIndex];
    }
}
