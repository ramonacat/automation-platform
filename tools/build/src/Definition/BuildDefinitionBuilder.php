<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function count;
use Ramona\AutomationPlatformLibBuild\Target;

final class BuildDefinitionBuilder
{
    /**
     * @var list<Target> $targets
     */
    private array $targets = [];

    /**
     * @internal
     */
    public function __construct()
    {
    }

    /**
     * @return $this
     */
    public function addTarget(Target $target): self
    {
        $this->targets[] = $target;

        return $this;
    }

    /**
     * @internal
     */
    public function build(): BuildDefinition
    {
        if (count($this->targets) === 0) {
            throw InvalidBuildDefinitionBuilder::noTargets();
        }

        return new BuildDefinition($this->targets);
    }
}
