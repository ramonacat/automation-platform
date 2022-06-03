<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function assert;
use Bramus\Ansi\Ansi;
use Closure;
use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\InvalidBuildDefinition;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use function Safe\realpath;

final class DefaultBuildDefinitionsLoader implements BuildDefinitionsLoader
{
    /** @var array<string, BuildDefinition> */
    private array $definitions = [];

    public function __construct(
        private readonly BuildFacts $buildFacts,
        private readonly Configuration $configuration,
        private readonly Ansi $ansi
    ) {
    }

    private function load(string $path): void
    {
        $path = realpath($path);
        /** @psalm-suppress UnresolvableInclude */
        $buildDefinition = require $path . DIRECTORY_SEPARATOR . 'build-config.php';

        if (!$buildDefinition instanceof Closure) {
            throw InvalidBuildDefinition::atPath($path);
        }

        $buildDefinitionBuilder = new BuildDefinitionBuilder($path, $this->ansi);
        ($buildDefinition)($buildDefinitionBuilder);
        $this->definitions[$path] = $buildDefinitionBuilder->build($this->buildFacts, $this->configuration);
    }

    private function get(string $path): BuildDefinition
    {
        $path = realpath($path);

        if (!isset($this->definitions[$path])) {
            $this->load($path);
        }

        return $this->definitions[$path];
    }

    public function target(TargetId $targetId): Target
    {
        $target = $this->get($targetId->path())->target($targetId->target());
        assert($targetId->toString() === $target->id()->toString());

        return $target;
    }

    /**
     * @return non-empty-list<string>
     */
    public function targetNames(string $workingDirectory): array
    {
        return $this->get($workingDirectory)->targetNames();
    }
}
