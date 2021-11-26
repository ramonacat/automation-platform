<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

interface ActionOutput
{
    public function pushSeparator(string $name): void;
    public function pushError(string $data): void;
    public function pushOutput(string $data): void;
}
