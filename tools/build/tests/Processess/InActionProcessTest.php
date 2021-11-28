<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Processess;

use const PHP_BINARY;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Processes\InActionProcess;

final class InActionProcessTest extends TestCase
{
    public function testCanPassStandardInput(): void
    {
        $process = new InActionProcess(
            [
                PHP_BINARY,
                __DIR__ . '/test-scripts/stdin_to_stdout.php',
            ],
            30
        );

        $output = $this->createMock(ActionOutput::class);
        $output->expects(self::once())->method('pushOutput')->with('abc');
        $process->run($output, 'abc');
    }
}
