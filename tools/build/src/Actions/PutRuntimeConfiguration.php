<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use Ramona\AutomationPlatformLibBuild\ActionOutput;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\Context;
use function Safe\file_put_contents;
use function Safe\json_encode;

final class PutRuntimeConfiguration implements BuildAction
{
    public function __construct(private string $path)
    {
    }

    public function execute(ActionOutput $output, Context $context, string $workingDirectory): BuildActionResult
    {
        file_put_contents($workingDirectory . DIRECTORY_SEPARATOR . $this->path, json_encode($context->configuration()->getRuntimeConfiguration(), JSON_THROW_ON_ERROR));

        return BuildActionResult::ok([]);
    }
}
