<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions;

use const PHP_BINARY;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

final class RunProcessTest extends TestCase
{
    public function testWillTimeout(): void
    {
        $action = new RunProcess(PHP_BINARY . ' ' . __DIR__ . '/test-scripts/runs-for-3-seconds.php', 1);

        $this->expectException(ProcessTimedOutException::class);
        $action->execute(
            $this->createMock(ActionOutput::class),
            Configuration::fromJsonString('{}')
        );
    }

    public function testWillReadStdOut(): void
    {
        $action = new RunProcess(PHP_BINARY . ' ' . __DIR__ . '/test-scripts/prints-test-to-stdout.php');

        $output = $this->createMock(ActionOutput::class);
        $output->expects(self::once())->method('pushOutput')->with('test');

        $action->execute(
            $output,
            Configuration::fromJsonString('{}')
        );
    }

    public function testWillReadStdErr(): void
    {
        $action = new RunProcess(PHP_BINARY . ' ' . __DIR__ . '/test-scripts/prints-test-to-stderr.php');

        $output = $this->createMock(ActionOutput::class);
        $output->expects(self::once())->method('pushError')->with('test');

        $action->execute(
            $output,
            Configuration::fromJsonString('{}')
        );
    }
}
