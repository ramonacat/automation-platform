<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use function Safe\copy;

final class CopyFile implements BuildAction
{
    public function __construct(private string $source, private string $target)
    {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildResult
    {
        copy(
            $workingDirectory . DIRECTORY_SEPARATOR . $this->source,
            $workingDirectory . DIRECTORY_SEPARATOR . $this->target
        );

        return BuildResult::ok([]);
    }
}
