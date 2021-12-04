<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\PHP;

use function array_merge;
use const DIRECTORY_SEPARATOR;
use function is_array;
use function is_string;
use JsonPath\JsonObject;
use function Safe\file_get_contents;
use function Safe\realpath;

final class LocalDependencyDetector
{
    /**
     * @return list<string>
     */
    public function forProject(string $projectPath): array
    {
        $projectComposerJsonPath = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';
        $composerJson = new JsonObject(file_get_contents($projectComposerJsonPath), true);

        /** @var mixed|false $repositories */
        $repositories = $composerJson->get('$.repositories');
        if ($repositories === false) {
            $repositories = [];
        }

        $packagePaths = [];
        foreach ($repositories as $repository) {
            if (!isset($repository['type'], $repository['url']) || $repository['type'] !== 'path') {
                continue;
            }

            if (!is_string($repository['url'])) {
                throw InvalidComposerJson::repositoryURLNotAString($projectComposerJsonPath);
            }

            $dependencyComposerJson = new JsonObject(file_get_contents($projectPath . DIRECTORY_SEPARATOR . $repository['url'] . DIRECTORY_SEPARATOR . 'composer.json'), true);
            /** @var mixed $name */
            $name = $dependencyComposerJson->get('$.name');

            if (!is_string($name)) {
                throw InvalidComposerJson::nameNotAString($projectComposerJsonPath);
            }

            $packagePaths[$name] = realpath($projectPath . DIRECTORY_SEPARATOR . $repository['url']);
        }

        $dependencyPaths = [];
        $requires = $composerJson->get('$.require');
        if (!is_array($requires)) {
            throw InvalidComposerJson::requireNotAnArray($projectComposerJsonPath);
        }
        $devRequires = $composerJson->get('$.require-dev');
        if (!is_array($devRequires)) {
            throw InvalidComposerJson::requireDevNotAnArray($projectComposerJsonPath);
        }
        $allPackages = array_merge($requires, $devRequires);
        foreach ($allPackages as $name => $_) {
            if (!is_string($name)) {
                throw InvalidComposerJson::dependencyNameNotAString($projectComposerJsonPath);
            }

            if (isset($packagePaths[$name])) {
                $dependencyPaths[] = $packagePaths[$name];
            }
        }

        return $dependencyPaths;
    }
}
