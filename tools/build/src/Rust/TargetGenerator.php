<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

use function array_filter;
use function array_map;
use function array_values;
use function md5;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration as TargetConfiguration;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetGenerator as TargetGeneratorInterface;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class TargetGenerator implements TargetGeneratorInterface
{
    /**
     * @var non-empty-list<Target>
     */
    private array $targets;

    public function __construct(private readonly string $projectDirectory, private DependencyDetector $dependencyDetector)
    {
        $this->targets = [
            new Target(new TargetId($this->projectDirectory, 'rust-clippy'), new RunProcess(['cargo', 'clippy', '--', '-D', 'clippy::pedantic', '-D', 'warnings'], timeoutSeconds: 600)),
            new Target(new TargetId($this->projectDirectory, 'rust-fmt-check'), new RunProcess(['cargo', 'fmt', '--', '--check'], timeoutSeconds: 600)),
            new Target(new TargetId($this->projectDirectory, 'rust-cargo-deny'), new RunProcess(['cargo', 'deny', 'check'], timeoutSeconds: 600)),
            new Target(new TargetId($this->projectDirectory, 'rust-fmt'), new RunProcess(['cargo', 'fmt'], timeoutSeconds: 600)),
            new Target(
                new TargetId($this->projectDirectory, 'rust-tests-unit'),
                new RunUnitTests(),
            ),
            new Target(
                new TargetId($this->projectDirectory, 'rust-unused-dependencies'),
                new RunProcess(
                    ['cargo', '+nightly', 'udeps', '--all-targets'],
                    timeoutSeconds: 600,
                    // The target directory is set to a temporary directory to avoid rebuilds (because udeps runs on nightly, the rest on stable)
                    additionalEnvironmentVariables: ['CARGO_TARGET_DIR' => '/tmp/' . md5($this->projectDirectory)]
                )
            ),
        ];
    }

    /**
     * @return non-empty-list<Target>
     */
    public function targets(BuildFacts $facts, TargetConfiguration $configuration): array
    {
        return $this->targets;
    }

    /**
     * @return list<TargetId>
     */
    private function buildTargetIds(): array
    {
        return array_values(array_map(
            fn (Target $t) => $t->id(),
            array_filter(
                $this->targets,
                static fn (Target $t) => $t->id()->target() !== 'rust-fmt'
            )
        ));
    }

    public function defaultTargetIds(DefaultTargetKind $kind): array
    {
        $buildDependencies = [];

        foreach ($this->dependencyDetector->forProject($this->projectDirectory) as $dependantProject) {
            $buildDependencies[] = new TargetId($dependantProject, 'build');
        }

        $buildDependencies = [...$buildDependencies, ...$this->buildTargetIds()];

        return match ($kind) {
            DefaultTargetKind::Build => $buildDependencies,
            DefaultTargetKind::Fix => [new TargetId($this->projectDirectory, 'rust-fmt')],
        };
    }
}
