<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Docker;

use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\Actions\Docker\BuildDockerImage;
use Ramona\AutomationPlatformLibBuild\Actions\Docker\LintDockerfile;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
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

    /**
     * @param list<TargetId> $additionalDependencies
     */
    public function __construct(private string $projectDirectory, private string $artifactKey, string $imageName, array $additionalDependencies = [], string $contextPath = '.', string $dockerFilePath = 'Dockerfile')
    {
        $this->targets = [
            new Target(
                $artifactKey . '-docker-build',
                new BuildDockerImage($artifactKey, $imageName, $contextPath, $dockerFilePath),
                [
                    new TargetId($projectDirectory, $this->artifactKey . '-docker-lint')
                ]
            ),
            new Target(
                $artifactKey . '-docker-lint',
                new LintDockerfile($this->projectDirectory . DIRECTORY_SEPARATOR . $dockerFilePath),
                $additionalDependencies
            )
        ];
    }

    public function targets(BuildFacts $facts, Configuration $configuration): array
    {
        return $this->targets;
    }

    public function defaultTargetIds(DefaultTargetKind $kind): array
    {
        return match ($kind) {
            DefaultTargetKind::Build => [new TargetId($this->projectDirectory, $this->artifactKey . '-docker-build')],
            DefaultTargetKind::Fix => [],
        };
    }
}
