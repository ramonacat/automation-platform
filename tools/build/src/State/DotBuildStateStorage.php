<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\State;

use const DIRECTORY_SEPARATOR;
use function dirname;
use function file_exists;
use function is_dir;
use RuntimeException;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function serialize;
use function unserialize;

final class DotBuildStateStorage implements StateStorage
{
    public function __construct(private string $rootPath)
    {
    }

    public function get(): State
    {
        $stateFilePath = $this->stateFilePath();
        if (file_exists($stateFilePath)) {
            $state = unserialize(file_get_contents($stateFilePath));

            if (!($state instanceof State)) {
                // todo decide what to do here... just pretend it does not exist? might be because of incompatibility between versions or something like that
                throw new RuntimeException('Invalid state!');
            }

            return $state;
        }

        return new State();
    }

    public function set(State $state): void
    {
        $stateFilePath = $this->stateFilePath();
        $stateFileDirectory = dirname($stateFilePath);

        if (!is_dir($stateFileDirectory)) {
            mkdir($stateFileDirectory, recursive: true);
        }

        file_put_contents($stateFilePath, serialize($state));
    }

    private function stateFilePath(): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . '.build/state';
    }
}
