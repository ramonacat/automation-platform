<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use const DIRECTORY_SEPARATOR;
use function Safe\realpath;

final class DefaultBuildDefinitionsLoader implements BuildDefinitionsLoader
{
    /** @var array<string, BuildDefinition> */
    private array $definitions = [];

    private function load(string $path): void
    {
        $path = realpath($path);
        /** @psalm-suppress UnresolvableInclude */
        $buildDefinition = require $path . DIRECTORY_SEPARATOR . 'build-config.php';

        if (!$buildDefinition instanceof BuildDefinition) {
            throw InvalidBuildDefinition::atPath($path);
        }

        $this->definitions[$path] = $buildDefinition;
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
        return $this->get($targetId->path())->target($targetId->target());
    }

    /**
     * @return non-empty-list<string>
     */
    public function getActionNames(string $workingDirectory): array
    {
        return $this->get($workingDirectory)->targetNames();
    }
}
