<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Rust;

interface DependencyDetector
{
    /**
     * @return list<string>
     */
    public function forProject(string $projectDirectory): array;
}
