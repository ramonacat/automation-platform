<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Output;

final class CollectedTargetOutput
{
    public function __construct(private string $standardOutput, private string $standardError)
    {
    }

    public function standardOutput(): string
    {
        return $this->standardOutput;
    }

    public function standardError(): string
    {
        return $this->standardError;
    }
}
