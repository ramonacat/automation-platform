<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

use function array_map;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

final class TargetGenerator
{
    /**
     * @var non-empty-list<Target>
     */
    private array $targets;

    public function __construct(private string $projectDirectory)
    {
        $this->targets = [
            new Target('rust-clippy', new RunProcess('cargo clippy -- -D clippy::pedantic -D warnings')),
            new Target('rust-fmt-check', new RunProcess('cargo fmt -- --check')),
            new Target('rust-tests-unit', new RunProcess('cargo test')),
            new Target('rust-unused-dependencies', new RunProcess('cargo +nightly udeps --all-targets')),
        ];
    }

    /**
     * @return non-empty-list<Target>
     */
    public function targets(): array
    {
        return $this->targets;
    }

    /**
     * @return non-empty-list<TargetId>
     */
    public function targetIds(): array
    {
        return array_map(fn (Target $t) => new TargetId($this->projectDirectory, $t->name()), $this->targets);
    }
}
