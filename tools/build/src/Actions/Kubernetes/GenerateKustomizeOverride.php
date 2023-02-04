<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions\Kubernetes;

use function basename;
use const DIRECTORY_SEPARATOR;
use function is_array;
use JsonPath\JsonObject;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use function Safe\file_put_contents;
use Symfony\Component\Yaml\Yaml;

final class GenerateKustomizeOverride implements BuildAction
{
    /**
     * @param list<KustomizeOverride> $overrides
     */
    public function __construct(
        private string $file,
        private string $overrideDirectory,
        private array $overrides
    ) {
    }

    public function execute(
        TargetOutput $output,
        Context $context,
        string $workingDirectory
    ): BuildResult {
        /** @var mixed $inputFile */
        $inputFile = Yaml::parseFile($workingDirectory . DIRECTORY_SEPARATOR . $this->file);
        $outputFile = new JsonObject();

        if (!is_array($inputFile)) {
            throw new InvalidInputFile($this->file);
        }

        $outputFile->set('$.apiVersion', $inputFile['apiVersion']);
        $outputFile->set('$.metadata', $inputFile['metadata']);
        $outputFile->set('$.kind', $inputFile['kind']);

        foreach ($this->overrides as $override) {
            $outputFile->set($override->jsonPath(), ($override->valueGenerator())($context));
        }

        file_put_contents($workingDirectory . DIRECTORY_SEPARATOR . $this->overrideDirectory . DIRECTORY_SEPARATOR . basename($this->file), Yaml::dump($outputFile->getValue()));

        return BuildResult::ok([]);
    }
}
