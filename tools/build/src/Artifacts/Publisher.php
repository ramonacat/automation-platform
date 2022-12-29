<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

use Bramus\Ansi\Ansi;

/**
 * @template T of Artifact
 */
interface Publisher
{
    /**
     * @return class-string<T>
     */
    public function publishes(): string;

    /**
     * @param T $artifact
     */
    public function publish(Artifact $artifact): void;

    public function print(Ansi $ansi): void;
}
