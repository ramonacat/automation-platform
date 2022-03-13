<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use const DIRECTORY_SEPARATOR;
use function file_exists;
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

    public function __construct(private string $projectDirectory, private Configuration $configuration)
    {
        $composerRequireCheckerConfigPath = $this->projectDirectory . DIRECTORY_SEPARATOR . 'composer-require-checker.json';
        $this->targets = [
            new Target(new TargetId($this->projectDirectory, 'php-type-check'), new RunProcess(['php', 'vendor/bin/psalm'])),
            new Target(new TargetId($this->projectDirectory, 'php-coding-standard'), new RunProcess(['php', 'vendor/bin/ecs'])),
            // The config file here is a temporary solution until https://github.com/maglnet/ComposerRequireChecker/pull/320 is done and merged
            new Target(
                new TargetId($this->projectDirectory, 'php-check-transitive-deps'),
                new RunProcess(
                    file_exists($composerRequireCheckerConfigPath)
                        ? [
                            'composer-require-checker',
                            'check',
                            '--config-file=' . $composerRequireCheckerConfigPath,
                            'composer.json',
                        ]
                        : [
                            'composer-require-checker',
                            'check',
                            'composer.json',
                        ]
                )
            ),
            new Target(new TargetId($this->projectDirectory, 'php-check-unused-deps'), new RunProcess(['composer-unused'])),
            new Target(new TargetId($this->projectDirectory, 'php-cs-fix'), new RunProcess(['php', 'vendor/bin/ecs', '--fix'])),
            new Target(new TargetId($this->projectDirectory, 'php-tests-unit'), new RunProcess(['php', 'vendor/bin/phpunit']), [new TargetId($this->projectDirectory, 'php-type-check')]),

        ];
    }

    public function targets(BuildFacts $facts, TargetConfiguration $configuration): array
    {
        return array_merge(
            $this->targets,
            [
                new Target(
                    new TargetId($this->projectDirectory, 'php-tests-mutation'),
                    new RunProcess(
                        [
                            'php',
                            'vendor/bin/infection',
                            '-j' . (string)$facts->logicalCores(),
                            '--min-msi=' . (string)$this->configuration->minMsi(),
                            '--min-covered-msi=' . (string)$this->configuration->minCoveredMsi()
                        ],
                        timeoutSeconds: 300
                    ),
                    [new TargetId($this->projectDirectory, 'php-tests-unit')]
                )
            ]
        );
    }

    /**
     * @return list<TargetId>
     */
    private function buildTargetIds(): array
    {
        return array_merge(
            [new TargetId($this->projectDirectory, 'php-tests-mutation')],
            array_values(
                array_map(
                    static fn (Target $t) => $t->id(),
                    array_filter(
                        $this->targets,
                        static fn (Target $target) => $target->id()->target() !== 'php-cs-fix'
                    )
                )
            )
        );
    }

    public function defaultTargetIds(DefaultTargetKind $kind): array
    {
        $buildDependencies = [];
        $deps = new LocalDependencyDetector();
        foreach ($deps->forProject($this->projectDirectory) as $dependantProjectPath) {
            $buildDependencies[] = new TargetId($dependantProjectPath, 'build');
        }

        $buildDependencies = array_merge($buildDependencies, $this->buildTargetIds());

        return match ($kind) {
            DefaultTargetKind::Build => $buildDependencies,
            DefaultTargetKind::Fix => [new TargetId($this->projectDirectory, 'php-cs-fix')],
        };
    }
}
