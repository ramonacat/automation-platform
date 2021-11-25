<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function count;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetGenerator;

final class BuildDefinitionBuilder
{
    /**
     * @var list<Target> $targets
     */
    private array $targets = [];

    /**
     * @var list<TargetGenerator>
     */
    private array $targetGenerators = [];

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
    public function build(BuildFacts $buildFacts, Configuration $configuration): BuildDefinition
    {
        foreach ($this->targetGenerators as $generator) {
            foreach ($generator->targets($buildFacts, $configuration) as $target) {
                $this->addTarget($target);
            }
        }

        if (count($this->targets) === 0) {
            throw InvalidBuildDefinitionBuilder::noTargets();
        }

        return new BuildDefinition($this->targets);
    }

    public function addTargetGenerator(TargetGenerator $targetGenerator): void
    {
        $this->targetGenerators[] = $targetGenerator;
    }
}
