<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

use function array_filter;
use function array_map;
use function array_values;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

final class TargetGenerator
{
    /**
     * @var non-empty-list<Target>
     */
    private array $targets;

    public function __construct(private string $projectDirectory, Configuration $configuration)
    {
        $this->targets = [
            new Target('php-type-check', new RunProcess(['php', 'vendor/bin/psalm'])),
            new Target('php-coding-standard', new RunProcess(['php', 'vendor/bin/ecs'])),
            new Target('php-check-transitive-deps', new RunProcess(['composer-require-checker', 'check', 'composer.json'])),
            new Target('php-check-unused-deps', new RunProcess(['composer', 'unused'])),
            new Target('php-cs-fix', new RunProcess(['php', 'vendor/bin/ecs', '--fix'])),

            new Target('php-tests-unit', new RunProcess(['php', 'vendor/bin/phpunit'])),
            new Target(
                'php-tests-mutation',
                new RunProcess(
                    [
                        'php',
                        'vendor/bin/infection',
                        // todo set the number of parallel runs dynamically, once it's supported in build
                        '-j6',
                        '--min-msi=' . (string)$configuration->minMsi(),
                        '--min-covered-msi=' . (string)$configuration->minCoveredMsi()
                    ],
                    timeoutSeconds: 120
                ),
                [new TargetId($this->projectDirectory, 'php-tests-unit')]
            ),
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
     * @return list<TargetId>
     */
    public function buildTargetIds(): array
    {
        return array_values(
            array_map(
                fn (Target $t) => new TargetId($this->projectDirectory, $t->name()),
                array_filter(
                    $this->targets,
                    static fn (Target $target) => $target->name() !== 'php-cs-fix'
                )
            )
        );
    }
}
