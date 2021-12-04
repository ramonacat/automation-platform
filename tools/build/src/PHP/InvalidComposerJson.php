<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

use RuntimeException;

final class InvalidComposerJson extends RuntimeException
{
    public static function repositoryURLNotAString(string $projectComposerJsonPath): self
    {
        return new self("Repository URL is not a string in {$projectComposerJsonPath}");
    }

    public static function nameNotAString(string $projectComposerJsonPath): self
    {
        return new self("Package name is not a string in {$projectComposerJsonPath}");
    }

    public static function dependencyNameNotAString(string $projectComposerJsonPath): self
    {
        return new self("Dependency name is not a string in {$projectComposerJsonPath}");
    }

    public static function requireNotAnArray(string $projectComposerJsonPath): self
    {
        return new self("The require section is not an array in {$projectComposerJsonPath}");
    }

    public static function requireDevNotAnArray(string $projectComposerJsonPath): self
    {
        return new self("The require-dev section is not an array in {$projectComposerJsonPath}");
    }
}
