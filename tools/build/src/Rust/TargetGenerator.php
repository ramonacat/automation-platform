<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

use function array_map;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration as TargetConfiguration;
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
            new Target('rust-tests-unit', new RunProcess(['cargo', 'test'], timeoutSeconds: 300)),
            new Target('rust-unused-dependencies', new RunProcess(['cargo', '+nightly', 'udeps', '--all-targets'], timeoutSeconds: 300)),
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
     * @return non-empty-list<TargetId>
     */
    public function buildTargetIds(): array
    {
        return array_map(fn (Target $t) => new TargetId($this->projectDirectory, $t->name()), $this->targets);
    }
}
