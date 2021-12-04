<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

use const DIRECTORY_SEPARATOR;
use JsonPath\JsonObject;
use Symfony\Component\Process\Process;

final class LocalDependencyDetector
{
    /**
     * @return list<string>
     */
    public function forProject(string $projectDirectory): array
    {
        $process = new Process(['cargo', 'metadata', '--frozen', '--all-features', '--color', 'never', '--manifest-path', $projectDirectory . DIRECTORY_SEPARATOR . 'Cargo.toml']);
        $process->mustRun();

        $metadata = new JsonObject($process->getOutput());

        $deps = $metadata->get('$.packages[*].dependencies[*].path');
        if ($deps === false) {
            return [];
        }

        return $deps;
    }
}
