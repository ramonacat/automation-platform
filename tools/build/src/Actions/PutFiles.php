<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use Closure;
use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use function Safe\file_put_contents;

final class PutFiles implements BuildAction
{
    /**
     * @var Closure(Context):array<string, string>
     */
    private Closure $generateContents;

    /**
     * @param callable(Context):array<string, string> $generateContents
     */
    public function __construct(callable $generateContents)
    {
        $this->generateContents = Closure::fromCallable($generateContents);
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        $contents = ($this->generateContents)($context);

        foreach ($contents as $path => $content) {
            file_put_contents(
                $workingDirectory . DIRECTORY_SEPARATOR . $path,
                $content
            );
        }

        return BuildResult::ok([]);
    }
}
