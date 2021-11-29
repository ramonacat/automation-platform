<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Bramus\Ansi\Ansi;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class StyledTargetOutput implements TargetOutput
{
    private string $standardOutput = '';
    private string $standardError = '';
    private string $output = '';

    public function __construct(private TargetId $id, private Ansi $ansi)
    {
        $startLine = '> Running target ' . $id->target() . ' from ' . $id->path() . '... ' . PHP_EOL;

        $this
            ->ansi
            ->text($startLine);
    }

    public function pushError(string $data): void
    {
        $this->standardError .= $data;
        $this->output .= $data;
    }

    public function pushOutput(string $data): void
    {
        $this->standardOutput .= $data;
        $this->output .= $data;
    }

    public function getCollectedStandardOutput(): string
    {
        return $this->standardOutput;
    }

    public function getCollectedStandardError(): string
    {
        return $this->standardError;
    }

    public function finalize(BuildActionResult $result): void
    {
        $startLine = '> Target ' . $this->id->target() . ' from ' . $this->id->path() . ' finished' . PHP_EOL;

        $this
            ->ansi
            ->text($startLine);
    }
}
