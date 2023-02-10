<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Processess;

use const PHP_BINARY;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Processes\DefaultInActionProcess;
use Tests\Ramona\AutomationPlatformLibBuild\DumbFiberRunner;

final class DefaultInActionProcessTest extends TestCase
{
    public function testCanPassStandardInput(): void
    {
        $process = new DefaultInActionProcess(
            __DIR__,
            [
                PHP_BINARY,
                __DIR__ . '/test-scripts/stdin_to_stdout.php',
            ],
            1
        );

        $result = null;
        $output = $this->createMock(TargetOutput::class);
        $output
            ->expects(self::atLeastOnce())
            ->method('pushOutput')
            ->willReturnCallback(
                function (string $data) use (&$result) {
                    if ($data !== '') {
                        $result = $data;
                    }
                }
            );

        DumbFiberRunner::run(
            fn () => $process->run($output, 'abc')
        );

        self::assertSame('abc', $result);
    }
}
