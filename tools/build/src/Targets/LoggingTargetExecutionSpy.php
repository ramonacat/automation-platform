<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;

use function microtime;
use function number_format;

use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\BuildResult;

final class LoggingTargetExecutionSpy implements TargetExecutionSpy
{
    /**
     * @var array<string, float>
     */
    private array $startTimes = [];

    /**
     * @var array<string, float>
     */
    private array $runningTimes = [];

    public function __construct(private string $logFilePath, private Ansi $ansi)
    {
    }

    /**
     * @param TargetId $targetId
     * @param array<TargetId> $dependencies
     */
    public function targetStarted(TargetId $targetId, array $dependencies): void
    {
        $this->startTimes[$targetId->toString()] = microtime(true);
    }
    
    /**
     * @param TargetId $targetId
     * @param BuildResult $result
     */
    public function targetFinished(TargetId $targetId, BuildResult $result): void
    {
        $this->runningTimes[$targetId->toString()] = microtime(true) - $this->startTimes[$targetId->toString()];
    }

    public function buildFinished(): void
    {
        $this
            ->ansi
            ->color([SGR::COLOR_FG_YELLOW_BRIGHT])
            ->bold()
            ->text('Build timings: ' . PHP_EOL)
            ->nostyle();

        foreach ($this->runningTimes as $targetId => $runningTime) {
            $this
                ->ansi
                ->text('    ' . $targetId)
                ->nostyle()
                ->text(': ')
                ->color([SGR::COLOR_FG_GREEN_BRIGHT])
                ->bold()
                ->text(number_format($runningTime, 3))
                ->text('s')
                ->nostyle()
                ->text(PHP_EOL);
        }
        
        $this->ansi->text(PHP_EOL);
    }
}
