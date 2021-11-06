<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Closure;
use function file_put_contents;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

/**
 * @api
 */
final class PutFile implements BuildAction
{
    private Closure $generateContents;

    /**
     * @param callable(Configuration):string $generateContents
     */
    public function __construct(private string $path, callable $generateContents)
    {
        $this->generateContents = Closure::fromCallable($generateContents);
    }

    public function execute(ActionOutput $output, Configuration $configuration): BuildActionResult
    {
        if (file_put_contents($this->path, ($this->generateContents)($configuration)) !== false) {
            return BuildActionResult::ok();
        }

        return BuildActionResult::fail("Failed to put file \"{$this->path}\"");
    }
}
