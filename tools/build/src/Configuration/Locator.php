<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use const DIRECTORY_SEPARATOR;
use function dirname;
use function file_exists;
use function Safe\getcwd;
use function sprintf;

final class Locator
{
    private const CONFIGURATION_FILENAME = 'configuration.json';
    private const CONFIGURATION_FILENAME_WITH_SUBTYPE = 'configuration.%s.json';

    public function locateConfigurationFile(?string $subtype = null): string
    {
        return $this->tryLocateConfigurationFile($subtype) ?? throw FileNotFound::create();
    }

    public function tryLocateConfigurationFile(?string $subtype = null): ?string
    {
        $directory = getcwd();

        $filenameToSearchFor = $subtype === null ? self::CONFIGURATION_FILENAME : sprintf(self::CONFIGURATION_FILENAME_WITH_SUBTYPE, $subtype);
        do {
            if ($directory === dirname($directory)) {
                return null;
            }
            $filename = $directory . DIRECTORY_SEPARATOR . $filenameToSearchFor;
            $directory = dirname($directory);
        } while (!file_exists($filename));

        return $filename;
    }
}
