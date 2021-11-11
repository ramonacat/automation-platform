<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Log;

use DateTimeImmutable;
use Monolog\Logger;
use const PHP_EOL;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Log\LogFormatter;

final class LogFormatterTest extends TestCase
{
    public function testCanFormatARecordWithEmptyContext(): void
    {
        $record = [
            'datetime' => new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
            'level' => Logger::INFO,
            'level_name' => Logger::getLevelName(Logger::INFO),
            'channel' => '',
            'context' => [],
            'message' => 'test test test'
        ];

        $formatter = new LogFormatter();
        $formatted = $formatter->format($record);

        self::assertEquals('[2021-01-01 00:00:00+00:00][INFO] test test test' . PHP_EOL, $formatted);
    }

    public function testCanFormatABatch(): void
    {
        $records = [
            [
                'datetime' => new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
                'level' => Logger::INFO,
                'level_name' => Logger::getLevelName(Logger::INFO),
                'channel' => '',
                'context' => [],
                'message' => 'test test test'
            ],
            [
                'datetime' => new DateTimeImmutable('2021-01-02 00:00:00+00:00'),
                'level' => Logger::INFO,
                'level_name' => Logger::getLevelName(Logger::INFO),
                'channel' => '',
                'context' => [],
                'message' => '123'
            ],
        ];

        $formatter = new LogFormatter();
        $formatted = $formatter->formatBatch($records);

        self::assertEquals(
            '[2021-01-01 00:00:00+00:00][INFO] test test test' . PHP_EOL .
            '[2021-01-02 00:00:00+00:00][INFO] 123' . PHP_EOL,
            $formatted
        );
    }

    public function testCanFormatARecordWithContext(): void
    {
        $record = [
            'datetime' => new DateTimeImmutable('2021-01-01 00:00:00+00:00'),
            'level' => Logger::INFO,
            'level_name' => Logger::getLevelName(Logger::INFO),
            'channel' => '',
            'context' => [
                'a' => 'b',
                'b' => 1,
                'c' => false,
                'd' => 1.0,
                'e' => (object)['a' => 1, 'b' => 2],
                'f' => 'a' . PHP_EOL . 'b'
            ],
            'message' => 'test test test'
        ];

        $formatter = new LogFormatter();
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
}
