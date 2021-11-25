<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
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

    public function __construct(private string $projectDirectory, private Configuration $configuration)
    {
        $this->targets = [
            new Target('php-type-check', new RunProcess(['php', 'vendor/bin/psalm'])),
            new Target('php-coding-standard', new RunProcess(['php', 'vendor/bin/ecs'])),
            new Target('php-check-transitive-deps', new RunProcess(['composer-require-checker', 'check', 'composer.json'])),
            new Target('php-check-unused-deps', new RunProcess(['composer', 'unused'])),
            new Target('php-cs-fix', new RunProcess(['php', 'vendor/bin/ecs', '--fix'])),

            new Target('php-tests-unit', new RunProcess(['php', 'vendor/bin/phpunit'])),

        ];
    }

    public function targets(BuildFacts $facts, TargetConfiguration $configuration): array
    {
        return array_merge($this->targets, [new Target(
            'php-tests-mutation',
            new RunProcess(
                [
                    'php',
                    'vendor/bin/infection',
                    // todo set the number of parallel runs dynamically, once it's supported in build
                    '-j' . (string)$facts->logicalCores(),
                    '--min-msi=' . (string)$this->configuration->minMsi(),
                    '--min-covered-msi=' . (string)$this->configuration->minCoveredMsi()
                ],
                timeoutSeconds: 120
            ),
            [new TargetId($this->projectDirectory, 'php-tests-unit')]
        )]);
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
