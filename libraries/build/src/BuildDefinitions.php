<?php

namespace Ramona\AutomationPlatformLibBuild;
use function Safe\realpath;
use const DIRECTORY_SEPARATOR;

final class BuildDefinitions
{
    /** @var array<string, BuildDefinition> */
    private array $definitions = [];

    public function load(string $path): void
    {
        $path = realpath($path);
        /** @psalm-suppress UnresolvableInclude */
        $buildDefinition = require $path . DIRECTORY_SEPARATOR . 'build-config.php';

        if(!$buildDefinition instanceof BuildDefinition) {
            throw InvalidBuildDefinition::atPath($path);
        }

        $this->definitions[$path] = $buildDefinition;
    }

    public function get(string $path): BuildDefinition
    {
        $path = realpath($path);

        if(!isset($this->definitions[$path])) {
            $this->load($path);
        }

        return $this->definitions[$path];
    }
}