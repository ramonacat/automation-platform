<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function array_merge;
use function count;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

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

    public function __construct(private string $path)
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

        return new BuildDefinition($this->path, $this->targets);
    }

    public function addTargetGenerator(TargetGenerator $targetGenerator): void
    {
        $this->targetGenerators[] = $targetGenerator;
    }

    /**
     * @param list<TargetId> $additionalDependencies
     */
    public function addDefaultTarget(DefaultTargetKind $kind, array $additionalDependencies = []): void
    {
        $dependencies = $additionalDependencies;

        foreach ($this->targetGenerators as $generator) {
            $dependencies = array_merge($dependencies, $generator->defaultTargetIds($kind));
        }

        $this->targets[] = new Target($kind->targetName(), new NoOp(), $dependencies);
    }
}
