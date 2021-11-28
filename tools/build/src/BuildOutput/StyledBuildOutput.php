<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use function array_slice;
use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use function ceil;
use function count;
use function explode;
use function implode;
use function mb_str_split;
use function mb_strlen;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\TerminalSize;
use function sprintf;

final class StyledBuildOutput implements ActionOutput, BuildOutput
{
    private string $standardOutput = '';
    private string $standardError = '';
    private string $output = '';
    private int $outputsPrinted = 0;
    private ?int $totalTargets = null;
    private int $targetsExecuted = 0;
    private int $startLineHeight = 0;

    public function __construct(private Ansi $ansi, private TerminalSize $terminalSize)
    {
    }

    public function startTarget(TargetId $id): void
    {
        $this->writeStartLine($id);
        $this
            ->ansi
            ->color([SGR::COLOR_FG_PURPLE])
            ->text(sprintf('(%s/%s)' . PHP_EOL, (string)($this->targetsExecuted + 1), (string)($this->totalTargets ?? '?')))
            ->nostyle();

        $this->standardError = '';
        $this->standardOutput = '';
        $this->outputsPrinted = 0;
        $this->targetsExecuted++;
    }

    public function getCollectedStandardOutput(): string
    {
        return $this->standardOutput;
    }

    public function getCollectedStandardError(): string
    {
        return $this->standardError;
    }

    public function pushError(string $data): void
    {
        $this->standardError .= $data;
        $this->output .= $data;

        $this->printOutputs();
    }

    public function pushOutput(string $data): void
    {
        $this->standardOutput .= $data;
        $this->output .= $data;

        $this->printOutputs();
    }

    public function pushSeparator(string $name): void
    {
        // todo figure out how to make it styled
        $this->output .= PHP_EOL . '--> ' . $name . PHP_EOL;
    }

    private function printOutputs(): void
    {
        $outputLines = array_slice(explode("\n", $this->output), -10);

        $wrappedLines = [];
        foreach ($outputLines as $line) {
            foreach (mb_str_split($line, $this->terminalSize->wrappingPoint()) as $chunk) {
                $wrappedLines[] = $chunk;
            }
        }

        $wrappedLines = array_slice($wrappedLines, -10);

        if ($this->outputsPrinted > 0) {
            $this
                ->ansi
                ->cursorUp($this->outputsPrinted);
        }

        $this->outputsPrinted = count($wrappedLines);

        $this
            ->ansi
            ->cursorBack(10000)
            ->eraseDisplayDown()
            ->eraseLine()
            ->color([SGR::COLOR_FG_CYAN])
            ->nostyle()
            ->text(implode(PHP_EOL, $wrappedLines) . PHP_EOL)
            ->color([SGR::COLOR_FG_CYAN])
            ->nostyle();
    }

    public function setTargetCount(int $count): void
    {
        $this->totalTargets = $count;
    }

    public function finalizeTarget(TargetId $targetId, BuildActionResult $result): void
    {
        $this
            ->ansi
            ->cursorUp($this->outputsPrinted + $this->startLineHeight)
            ->eraseDisplayDown()
            ->cursorBack(10000)
            ->eraseLine();

        $this->writeStartLine($targetId);

        if (!$result->hasSucceeded()) {
            $this
                ->ansi
                ->color([SGR::COLOR_FG_RED])
                ->text('[❌]' . PHP_EOL)
                ->nostyle();

            $this
                ->ansi
                ->text($this->output . PHP_EOL);
        } else {
            $this
                ->ansi
                ->color([SGR::COLOR_FG_GREEN])
                ->text('[✔]' . PHP_EOL)
                ->nostyle();
        }
    }

    private function writeStartLine(TargetId $id): void
    {
        $startLine = '> Running target ' . $id->target() . ' from ' . $id->path() . '... ';
        $this->startLineHeight = (int)ceil(mb_strlen($startLine) / ($this->terminalSize->wrappingPoint()));
        $this
            ->ansi
            ->text($startLine);
    }
}
