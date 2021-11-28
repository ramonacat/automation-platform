<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Closure;
use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Context;
use function Safe\file_put_contents;

/**
 * @api
 */
final class PutFile implements BuildAction
{
    private Closure $generateContents;

    /**
     * @param callable(Context):string $generateContents
     */
    public function __construct(private string $path, callable $generateContents)
    {
        $this->generateContents = Closure::fromCallable($generateContents);
    }

    public function execute(ActionOutput $output, Context $context, string $workingDirectory): BuildActionResult
    {
        file_put_contents($workingDirectory . DIRECTORY_SEPARATOR . $this->path, ($this->generateContents)($context));

        return BuildActionResult::ok([]);
    }
}
