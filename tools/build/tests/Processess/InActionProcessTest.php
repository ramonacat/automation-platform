<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Processess;

use const PHP_BINARY;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;
use Tests\Ramona\AutomationPlatformLibBuild\DumbFiberRunner;

final class InActionProcessTest extends TestCase
{
    public function testCanPassStandardInput(): void
    {
        $process = new InActionProcess(
            __DIR__,
            [
                PHP_BINARY,
                __DIR__ . '/test-scripts/stdin_to_stdout.php',
            ],
            30
        );

        $output = $this->createMock(TargetOutput::class);
        $output->expects(self::once())->method('pushOutput')->with('abc');

        DumbFiberRunner::run(
            fn () => $process->run($output, 'abc')
        );
    }
}
