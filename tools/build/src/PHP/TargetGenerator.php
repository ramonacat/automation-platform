<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

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

    public function __construct(private string $projectDirectory, int $minMsi = 100, int $minCoveredMsi = 100)
    {
        $this->targets = [
            new Target('php-coding-standard', new RunProcess(['php', 'vendor/bin/ecs'])),
            new Target('php-type-check', new RunProcess(['php', 'vendor/bin/psalm'])),
            new Target('php-tests-unit', new RunProcess(['php', 'vendor/bin/phpunit'])),
            // todo set the number of parallel runs dynamically, once it's supported in build
            new Target('php-tests-mutation', new RunProcess(['php', 'vendor/bin/infection', '-j6', '--min-msi=' . (string)$minMsi, '--min-covered-msi=' . (string)$minCoveredMsi], timeoutSeconds: 120)),
            new Target('php-check-transitive-deps', new RunProcess(['composer-require-checker', 'check', 'composer.json'])),
            new Target('php-check-unused-deps', new RunProcess(['composer', 'unused'])),
            new Target('php-cs-fix', new RunProcess(['php', 'vendor/bin/ecs', '--fix'])),
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
