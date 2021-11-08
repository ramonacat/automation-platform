<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Closure;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use function Safe\file_put_contents;

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
        file_put_contents($this->path, ($this->generateContents)($configuration));

        return BuildActionResult::ok();
    }
}
