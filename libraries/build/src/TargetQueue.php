<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

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

    public function hasId(string $id): bool
    {
        // todo this is O(N), probably could be O(1) with a hashmap
        foreach ($this->queue as $dependency) {
            if ($dependency->id() === $id) {
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
}
