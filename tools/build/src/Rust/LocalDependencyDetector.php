<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

use const DIRECTORY_SEPARATOR;
use JsonPath\JsonObject;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

final class LocalDependencyDetector
{
    /**
     * @return list<string>
     */
    public function forProject(string $projectDirectory): array
    {
        $process = new Process(['cargo', 'metadata', '--locked', '--all-features', '--color', 'never', '--manifest-path', $projectDirectory . DIRECTORY_SEPARATOR . 'Cargo.toml']);
        $process->mustRun();

        $metadata = new JsonObject($process->getOutput());
        /** @var mixed|false $deps */
        $deps = $metadata->get('$.packages[*].dependencies[*].path');
        if ($deps === false) {
            return [];
        }

        Assert::isList($deps);
        Assert::allString($deps);

        return $deps;
    }
}
