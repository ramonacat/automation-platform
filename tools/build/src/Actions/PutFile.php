<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Closure;
use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use function Safe\file_put_contents;

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

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        file_put_contents(
            $workingDirectory . DIRECTORY_SEPARATOR . $this->path,
            ($this->generateContents)($context)
        );

        return BuildResult::ok([]);
    }
}
