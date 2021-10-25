<?php

namespace Ramona\AutomationPlatformLibBuild;

use SplQueue;

final class DependencyQueue
{
    /**
     * @var SplQueue<Dependency>
     */
    private SplQueue $queue;

    public function __construct()
    {
        /** @var SplQueue<Dependency> $this->queue */
        $this->queue = new SplQueue();
    }

    public function push(Dependency $value): void
    {
        $this->queue->push($value);
    }

    public function pop(): Dependency
    {
        return $this->queue->shift();
    }

    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }
}