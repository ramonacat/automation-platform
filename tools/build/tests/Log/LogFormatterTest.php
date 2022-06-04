<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Log;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use const PHP_EOL;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Log\LogFormatter;
use RuntimeException;

final class LogFormatterTest extends TestCase
{
    public function testCanFormatARecordWithEmptyContext(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
            '',
            Level::Info,
            'test test test',
            [],
            []
        );

        $formatter = new LogFormatter(false);
        $formatted = $formatter->format($record);

        self::assertEquals('[2021-01-01 00:00:00+00:00][INFO] test test test' . PHP_EOL, $formatted);
    }

    public function testCanFormatABatch(): void
    {
        $records = [
            new LogRecord(
                new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
                '',
                Level::Info,
                'test test test',
                [],
                []
            ),
            new LogRecord(
                new DateTimeImmutable('2021-01-02 00:00:00+00:00'),
                '',
                Level::Info,
                '123',
                [],
                []
            ),
        ];

        $formatter = new LogFormatter(false);
        $formatted = $formatter->formatBatch($records);

        self::assertEquals(
            '[2021-01-01 00:00:00+00:00][INFO] test test test' . PHP_EOL .
            '[2021-01-02 00:00:00+00:00][INFO] 123' . PHP_EOL,
            $formatted
        );
    }

    public function testCanFormatARecordWithContext(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
            '',
            Level::Info,
            'test test test',
            [
                'a' => 'b',
                'b' => 1,
                'c' => false,
                'd' => 1.0,
                'e' => (object)['a' => 1, 'b' => 2],
                'f' => 'a' . PHP_EOL . 'b'
            ],
            []
        );

        $formatter = new LogFormatter(false);
        $formatted = $formatter->format($record);

        self::assertEquals(
            '[2021-01-01 00:00:00+00:00][INFO] test test test' . PHP_EOL
            . 'a: b' . PHP_EOL
            . 'b: 1' . PHP_EOL
            . 'c: false' . PHP_EOL
            . 'd: 1.0000' . PHP_EOL
            . 'e:' . PHP_EOL
            . '{' . "\n"
            . '    "a": 1,' . "\n"
            . '    "b": 2' . "\n"
            . '}' . PHP_EOL
            . 'f:' . PHP_EOL
            . 'a' . PHP_EOL
            . 'b' . PHP_EOL,
            $formatted
        );
    }

    public function testWillPrintExceptionDetailsInLocalMode(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
            '',
            Level::Info,
            'test test test',
            ['ex' => new RuntimeException('msg')],
            []
        );

        $formatter = new LogFormatter(false);
        $formatted = $formatter->format($record);

        self::assertStringStartsWith(
            '[2021-01-01 00:00:00+00:00][INFO] test test test' . PHP_EOL
            . 'ex:' . PHP_EOL
            . 'RuntimeException: msg' . PHP_EOL
            . '#0 ',
            $formatted
        );
    }

    public function testWillHideExceptionDetaisInCIMode(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
            '',
            Level::Info,
            'test test test',
            ['ex' => new RuntimeException('msg'), 'a' => 'b'],
            []
        );

        $formatter = new LogFormatter(true);
        $formatted = $formatter->format($record);

        self::assertEquals(
            '[2021-01-01 00:00:00+00:00][INFO] test test test' . PHP_EOL
            . 'ex: [RuntimeException][running in CI, exception details were redacted]' . PHP_EOL
            . 'a: b' . PHP_EOL,
            $formatted
        );
    }
}
