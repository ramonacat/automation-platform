<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

use function array_filter;
use function array_map;
use function array_values;
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

    public function __construct(private string $projectDirectory)
    {
        $this->targets = [
            new Target('rust-clippy', new RunProcess(['cargo', 'clippy', '--', '-D', 'clippy::pedantic', '-D', 'warnings'], timeoutSeconds: 300)),
            new Target('rust-fmt-check', new RunProcess(['cargo', 'fmt', '--', '--check'], timeoutSeconds: 300)),
            new Target('rust-fmt', new RunProcess(['cargo', 'fmt'], timeoutSeconds: 300)),
            new Target('rust-tests-unit', new RunProcess(['cargo', 'test'], timeoutSeconds: 300)),
            new Target('rust-unused-dependencies', new RunProcess(['cargo', '+nightly', 'udeps', '--all-targets'], timeoutSeconds: 300)),
            new Target('rust-cargo-audit', new RunProcess(['cargo', 'audit'], timeoutSeconds: 300)),
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
            fn (Target $t) => new TargetId($this->projectDirectory, $t->name()),
            array_filter(
                $this->targets,
                static fn (Target $t) => $t->name() !== 'rust-fmt'
            )
        ));
    }

    public function defaultTargetIds(DefaultTargetKind $kind): array
    {
        return match ($kind) {
            DefaultTargetKind::Build => $this->buildTargetIds(),
            DefaultTargetKind::Fix => [new TargetId($this->projectDirectory, 'rust-fmt')],
        };
    }
}
