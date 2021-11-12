<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use const DIRECTORY_SEPARATOR;
use function dirname;
use function file_exists;
use function Safe\getcwd;

final class Locator
{
    private const CONFIGURATION_FILENAME = 'configuration.json';

    public function locateConfigurationFile(): string
    {
        $directory = getcwd();

        do {
            if ($directory === dirname($directory)) {
                throw FileNotFound::create();
            }
            $filename = $directory . DIRECTORY_SEPARATOR . self::CONFIGURATION_FILENAME;
            $directory = dirname($directory);
        } while (!file_exists($filename));

        return $filename;
    }
}
