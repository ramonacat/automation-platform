<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use function debug_backtrace;
use const FILE_APPEND;
use function file_put_contents;
use function microtime;
use function number_format;
use const PHP_EOL;
use function print_r;
use Ramona\AutomationPlatformLibBuild\BuildResult;

final class LoggingTargetExecutionSpy implements TargetExecutionSpy
{
    /**
     * @var array<string, float>
     */
    private array $startTimes = [];

    public function __construct(private string $logFilePath)
    {
        file_put_contents($this->logFilePath, print_r(debug_backtrace(), true) . PHP_EOL);
    }

    /**
     * @param TargetId $targetId
     * @param array<TargetId> $dependencies
     */
    public function targetStarted(TargetId $targetId, array $dependencies): void
    {
        $this->startTimes[$targetId->toString()] = microtime(true);
        file_put_contents($this->logFilePath, 'Started:' . $targetId->toString() . PHP_EOL, FILE_APPEND);
    }
    
    /**
     *
     * @param TargetId $targetId
     * @param BuildResult $result
     */
    public function targetFinished(TargetId $targetId, BuildResult $result): void
    {
        $duration = microtime(true) - $this->startTimes[$targetId->toString()];
        file_put_contents($this->logFilePath, 'Finished:' . $targetId->toString() . ' in ' . number_format($duration, 3) . 's' . PHP_EOL, FILE_APPEND);
    }
}
