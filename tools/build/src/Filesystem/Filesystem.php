<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Filesystem;

interface Filesystem
{
    public function readFile(string $path): string;
    public function realpath(string $path): string;
}
