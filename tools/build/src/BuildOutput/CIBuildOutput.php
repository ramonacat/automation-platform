<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use function strlen;

final class CIBuildOutput implements BuildOutput
{
    private string $standardOutput = '';
    private string $standardError = '';
    private int $targetCount = 0;
    private int $currentTarget = 0;
    private bool $hadASeparator = false;

    public function __construct(private Ansi $ansi)
    {
    }

    public function pushError(string $data): void
    {
        $this->writeWithColoredPrefix('[E]', [SGR::COLOR_FG_RED], $data);
        $this->standardError .= $data;
    }

    public function pushOutput(string $data): void
    {
        $this->writeWithColoredPrefix('[O]', [SGR::COLOR_FG_CYAN], $data);
        $this->standardOutput .= $data;
    }

    public function pushSeparator(string $name): void
    {
        if ($this->hadASeparator) {
            $this->ansi->text('::endgroup::');
        }

        $this->ansi->text(PHP_EOL . '::group::' . $name . PHP_EOL);
        $this->hadASeparator = true;
    }

    public function setTargetCount(int $count): void
    {
        $this->targetCount = $count;
        $this->currentTarget = 1;
    }

    public function startTarget(TargetId $id): void
    {
        $this
            ->ansi
            ->nostyle()
            ->color([SGR::COLOR_FG_CYAN])
            ->text("($this->currentTarget/$this->targetCount) Running target {$id->toString()} " . PHP_EOL)
            ->nostyle();

        $this->currentTarget++;
        $this->hadASeparator = false;
    }

    public function getCollectedStandardOutput(): string
    {
        return $this->standardOutput;
    }

    public function getCollectedStandardError(): string
    {
        return $this->standardError;
    }

    public function finalizeTarget(TargetId $targetId, BuildActionResult $result): void
    {
        if ($this->hadASeparator) {
            $this
                ->ansi
                ->text(PHP_EOL)
                ->text('::endgroup::')
                ->text(PHP_EOL);
        }
        $color = $result->hasSucceeded() ? [SGR::COLOR_FG_GREEN] : [SGR::COLOR_FG_RED];
        $message = $result->hasSucceeded() ? "succeeded" : "failed: " . ($result->getMessage() ?? '');

        $this
            ->ansi
            ->nostyle()
            ->color($color)
            ->text(PHP_EOL . $message . PHP_EOL)
            ->nostyle();
    }

    private bool $shouldStartWithPrefix = false;

    /**
     * @param non-empty-list<string> $color
     */
    public function writeWithColoredPrefix(
        string $prefix,
        array $color,
        string $data
    ): void {
        $line = '';
        for ($i = 0, $iMax = strlen($data); $i < $iMax; $i++) {
            if ($data[$i] === "\n") {
                $this->writeChunk($line, $color, $prefix);
                $this->ansi->text(PHP_EOL);
                $line = '';
                $this->shouldStartWithPrefix = true;
            } else {
                $line .= $data[$i];
            }
        }

        if ($line !== '') {
            $this->writeChunk($line, $color, $prefix);
        }
    }

    /**
     * @param non-empty-list<string> $color
     */
    private function writeChunk(string $line, array $color, string $prefix): void
    {
        if ($this->shouldStartWithPrefix) {
            $this
                ->ansi
                ->nostyle()
                ->color($color)
                ->text("$prefix ")
                ->nostyle();
            $this->shouldStartWithPrefix = false;
        }

        $this->ansi->text($line);
    }
}
