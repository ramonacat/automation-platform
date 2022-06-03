<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use const DIRECTORY_SEPARATOR;
use Exception;
use JsonPath\JsonObject;
use const PHP_EOL;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

final class LocalDependencyDetector
{
    public function __construct(private readonly Ansi $ansi)
    {
    }

    /**
     * @return list<string>
     */
    public function forProject(string $projectDirectory): array
    {
        try {
            $process = new Process(['cargo', 'metadata', '--all-features', '--color', 'never', '--manifest-path', $projectDirectory . DIRECTORY_SEPARATOR . 'Cargo.toml']);
            $process->mustRun();
        } catch (Exception $exception) {
            $this
                ->ansi
                ->color([SGR::COLOR_FG_RED])
                ->text('Failed to run `cargo metadata`')
                ->nostyle()
                ->text((string)$exception . PHP_EOL);

            throw $exception;
        }

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
