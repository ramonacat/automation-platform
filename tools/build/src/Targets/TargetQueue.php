<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets;

use function array_diff;
use function array_map;
use function array_values;
use function iterator_to_array;
use SplQueue;

final class TargetQueue
{
    /**
     * @var SplQueue<TargetId>
     */
    private SplQueue $queue;

    public function __construct()
    {
        /** @var SplQueue<TargetId> $this->queue */
        $this->queue = new SplQueue();
    }

    /**
     * @param list<TargetId> $dependencies
     */
    public static function fromArray(array $dependencies): self
    {
        $queue = new self();
        foreach ($dependencies as $dependency) {
            $queue->enqueue($dependency);
        }

        return $queue;
    }

    public function hasId(TargetId $id): bool
    {
        // todo this is O(N), probably could be O(1) with a hashmap
        foreach ($this->queue as $dependency) {
            if ($dependency->toString() === $id->toString()) {
                return true;
            }
        }

        return false;
    }

    public function enqueue(TargetId $value): void
    {
        $this->queue->enqueue($value);
    }

    public function dequeue(): TargetId
    {
        return $this->queue->dequeue();
    }

    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }

    public function count(): int
    {
        return $this->queue->count();
    }

    public function __debugInfo(): array
    {
        return array_map(
            static fn (TargetId $t) => $t->toString(),
            $this->asArray()
        );
    }

    public function equals(TargetQueue $other): bool
    {
        return array_diff($this->__debugInfo(), $other->__debugInfo()) === [];
    }

    /**
     * @return list<TargetId>
     */
    public function asArray(): array
    {
        return array_values(iterator_to_array($this->queue));
    }
}
