<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use function array_slice;
use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use function count;
use function explode;
use function implode;
use const PHP_EOL;
use function sprintf;

final class StyledBuildOutput
{
    private string $standardOutput = '';
    private string $standardError = '';
    private int $outputsPrinted = 0;
    private ?int $totalTargets = null;
    private int $targetsExecuted = 0;

    public function __construct(private Ansi $ansi)
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

    public function writeStandardOutput(string $data): void
    {
        $this->standardOutput .= $data;

        $this->printOutputs();
    }

    public function writeStandardError(string $data): void
    {
        $this->standardError .= $data;

        $this->printOutputs();
    }

    private function printOutputs(): void
    {
        $standardOutputLines = array_slice(explode("\n", $this->standardOutput), -5);
        $standardErrorLines = array_slice(explode("\n", $this->standardError), -5);

        if ($this->outputsPrinted > 0) {
            $this
                ->ansi
                ->cursorUp($this->outputsPrinted);
        }

        $this->outputsPrinted = count($standardOutputLines) + count($standardErrorLines) + 2;

        $this
            ->ansi
            ->cursorBack(10000)
            ->eraseDisplayDown()
            ->eraseLine()
            ->color([SGR::COLOR_FG_CYAN])
            ->text('>> STDOUT' . PHP_EOL)
            ->nostyle()
            ->text(implode(PHP_EOL, $standardOutputLines) . PHP_EOL)
            ->color([SGR::COLOR_FG_CYAN])
            ->text('>> STDERR' . PHP_EOL)
            ->nostyle()
            ->text(implode(PHP_EOL, $standardErrorLines) . PHP_EOL)
            ->nostyle();
    }

    public function finalizeTarget(TargetId $targetId, BuildActionResult $result): void
    {
        $this
            ->ansi
            ->cursorUp(1 + $this->outputsPrinted)
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
                ->color([SGR::COLOR_FG_CYAN])
                ->text('>> STDOUT' . PHP_EOL)
                ->nostyle()
                ->text($this->standardOutput . PHP_EOL)
                ->nostyle()
                ->color([SGR::COLOR_FG_CYAN])
                ->text('>> STDERR' . PHP_EOL)
                ->nostyle()
                ->text($this->standardError . PHP_EOL)
                ->nostyle();
        } else {
            $this
                ->ansi
                ->color([SGR::COLOR_FG_GREEN])
                ->text('[✔]' . PHP_EOL)
                ->nostyle();
        }
    }

    /**
     * @param TargetId $id
     */
    public function writeStartLine(TargetId $id): void
    {
        $this
            ->ansi
            ->text('> Running target ' . $id->target() . ' from ' . $id->path() . '... ');
    }

    public function setTargetCount(int $count): void
    {
        $this->totalTargets = $count;
    }
}
