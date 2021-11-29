<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use Fiber;
use RuntimeException;

final class DumbFiberRunner
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function run(callable $callback)
    {
        $fiber = new Fiber(function () use ($callback) {
            Fiber::suspend($callback());
        });

        /** @var T|null $result */
        $result = $fiber->start();

        while ($result === null) {
            if ($fiber->isSuspended()) {
                /** @var T|null $result */
                $result = $fiber->resume();
            } elseif ($fiber->isTerminated()) {
                throw new RuntimeException('Fiber terminated without returning a value');
            }
        }

        return $result;
    }
}
