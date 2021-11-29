<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use const PHP_BINARY;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Context;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Tests\Ramona\AutomationPlatformLibBuild\DumbFiberRunner;

final class RunProcessTest extends TestCase
{
    public function testWillTimeout(): void
    {
        $action = new RunProcess([PHP_BINARY,  __DIR__ . '/test-scripts/runs-for-3-seconds.php'], [], 1);

        $this->expectException(ProcessTimedOutException::class);
        DumbFiberRunner::run(
            fn () =>
                $action->execute(
                    $this->createMock(TargetOutput::class),
                    $this->createContext(),
                    __DIR__
                )
        );
    }

    public function testWillReadStdOut(): void
    {
        $action = new RunProcess([PHP_BINARY, __DIR__ . '/test-scripts/prints-test-to-stdout.php']);

        $output = $this->createMock(TargetOutput::class);
        $output->expects(self::once())->method('pushOutput')->with('test');

        DumbFiberRunner::run(
            fn () =>
                $action->execute(
                    $output,
                    $this->createContext(),
                    __DIR__
                )
        );
    }

    public function testWillReadStdErr(): void
    {
        $action = new RunProcess([PHP_BINARY, __DIR__ . '/test-scripts/prints-test-to-stderr.php']);

        $output = $this->createMock(TargetOutput::class);
        $output->expects(self::once())->method('pushError')->with('test');

        DumbFiberRunner::run(
            fn () =>
                $action->execute(
                    $output,
                    $this->createContext(),
                    __DIR__
                )
        );
    }

    public function testWillReturnSuccessIfTheCommandIsSuccessful(): void
    {
        $action = new RunProcess([PHP_BINARY, __DIR__ . '/test-scripts/prints-test-to-stdout.php']);

        $result = DumbFiberRunner::run(
            fn () =>
                $action->execute(
                    $this->createMock(TargetOutput::class),
                    $this->createContext(),
                    __DIR__
                )
        );

        self::assertTrue($result->hasSucceeded());
    }

    private function createContext(): Context
    {
        return ContextFactory::create();
    }
}
