<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class StyledBuildOutput implements BuildOutput
{
    public function __construct(private Ansi $ansi)
    {
    }

    public function startTarget(TargetId $id): TargetOutput
    {
        return new StyledTargetOutput($id, $this->ansi);
    }

    public function finalizeBuild(array $results): void
    {
        foreach ($results as $targetId => $result) {
            [$result, $output] = $result;

            if (!$result->hasSucceeded()) {
                $this
                    ->ansi
                    ->color([SGR::COLOR_FG_RED])
                    ->text('[❌]')
                    ->nostyle();
            } else {
                $this
                    ->ansi
                    ->color([SGR::COLOR_FG_GREEN])
                    ->text('[✔]')
                    ->nostyle();
            }

            $this
                ->ansi
                ->text(' ')
                ->text($targetId)
                ->text(PHP_EOL);

            if (!$result->hasSucceeded()) {
                $stdout = $output->getCollectedStandardOutput();
                if ($stdout !== '') {
                    $this
                        ->ansi
                        ->color([SGR::COLOR_FG_CYAN])
                        ->text('>>> STDOUT')
                        ->text(PHP_EOL)
                        ->nostyle()
                        ->text($stdout)
                        ->nostyle()
                        ->text(PHP_EOL);
                }

                $stderr = $output->getCollectedStandardError();
                if ($stderr !== '') {
                    $this->ansi
                        ->color([SGR::COLOR_FG_CYAN])
                        ->text('>>> STDERR')
                        ->text(PHP_EOL)
                        ->nostyle()
                        ->text($stderr)
                        ->nostyle()
                        ->text(PHP_EOL);
                }
            }
        }
    }
}
