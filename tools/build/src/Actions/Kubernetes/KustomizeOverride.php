<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Kubernetes;

use Closure;
use Ramona\AutomationPlatformLibBuild\Context;

final class KustomizeOverride
{
    /**
     * @var Closure(Context):mixed $generateValue
     */
    private Closure $valueGenerator;

    /**
     * @param callable(Context):mixed $valueGenerator
     */
    public function __construct(private string $jsonPath, callable $valueGenerator)
    {
        $this->valueGenerator = Closure::fromCallable($valueGenerator);
    }

    public function jsonPath(): string
    {
        return $this->jsonPath;
    }

    /**
     * @return Closure(Context):mixed
     */
    public function valueGenerator(): Closure
    {
        return $this->valueGenerator;
    }
}
