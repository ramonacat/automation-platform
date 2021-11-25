<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\Targets\TargetQueue;
use function Safe\realpath;

final class TargetQueueTest extends TestCase
{
    public function testCanDequeueAfterEnqueuing(): void
    {
        $queue = new TargetQueue();
        $target = new TargetId('.', 'test');
        $queue->enqueue($target);
        $queue->enqueue(new TargetId('.', 'test2'));

        self::assertSame($target, $queue->dequeue());
    }

    public function testCanBeCounted(): void
    {
        $queue = new TargetQueue();
        $queue->enqueue(new TargetId('.', 'test'));

        self::assertEquals(1, $queue->count());
    }

    public function testPrintsAllTheTargetsInDebugRepresentation(): void
    {
        $queue = new TargetQueue();
        $queue->enqueue(new TargetId('.', 'test'));
        $queue->enqueue(new TargetId('.', 'test2'));

        $cwd = realpath('.');
        self::assertEquals([$cwd . ':test', $cwd . ':test2'], $queue->__debugInfo());
    }

    /**
     * @param list<TargetId> $haystack
     * @dataProvider dataHasId
     */
    public function testHasId(bool $expectsFound, TargetId $needle, array $haystack): void
    {
        $queue = TargetQueue::fromArray($haystack);

        self::assertSame($expectsFound, $queue->hasId($needle));
    }

    /**
     * @return iterable<array-key, array{0:bool,1:TargetId,2:list<TargetId>}>
     */
    public function dataHasId(): iterable
    {
        yield [
            true,
            new TargetId('.', 'test'),
            [
                new TargetId('.', 'test'),
                new TargetId('.', 'test2'),
            ]
        ];
        yield [
            false,
            new TargetId('.', 'test3'),
            [
                new TargetId('.', 'test'),
                new TargetId('.', 'test2'),
            ]
        ];
    }
}
