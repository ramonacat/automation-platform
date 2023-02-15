<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Output;

use Bramus\Ansi\Ansi;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class StyledTargetOutput implements TargetOutput
{
    private string $standardOutput = '';
    private string $standardError = '';

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
    }

    public function pushOutput(string $data): void
    {
        $this->standardOutput .= $data;
    }

    public function dependenciesCompleted(): void
    {
        $this
            ->ansi
            ->text('> Dependencies completed for ' . $this->id->target() . ' from ' . $this->id->path() . PHP_EOL);
    }

    public function finalize(BuildResult $result): CollectedTargetOutput
    {
        $startLine = '> Target ' . $this->id->target() . ' from ' . $this->id->path() . ' finished' . PHP_EOL;

        $this
            ->ansi
            ->text($startLine);

        return new CollectedTargetOutput($this->standardOutput, $this->standardError);
    }
}
