<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\BuildResultWithReason;
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

            [$icon, $color] = match ($result->reason()) {
                BuildResultWithReason::FailDependencyFailed => ['⬤', SGR::COLOR_FG_YELLOW],
                BuildResultWithReason::FailExecutionFailure => ['❌', SGR::COLOR_FG_RED],
                BuildResultWithReason::OkBuilt => ['✔', SGR::COLOR_FG_GREEN_BRIGHT],
                BuildResultWithReason::OkFromCache => ['☑', SGR::COLOR_FG_GREEN],
            };

            $this
                ->ansi
                ->color([$color])
                ->text('[')
                ->text($icon)
                ->text(']')
                ->nostyle();

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
