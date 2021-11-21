<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformToolDependabotConfigChecker;

use function count;
use function is_array;
use function is_int;
use function is_string;
use const JSON_PRETTY_PRINT;
use function Safe\array_flip;
use function Safe\json_encode;
use Symfony\Component\Yaml\Yaml;

final class Checker
{
    /**
     * @param list<string> $projectPaths
     */
    public function __construct(private array $projectPaths, private CheckerOutput $output)
    {
    }

    public function validate(string $dependabotConfig): int
    {
        $config = Yaml::parse($dependabotConfig);

        if (!isset($config['updates']) || !is_array($config['updates'])) {
            $this->output->invalid('"updates" key is not an array');

            return 1;
        }

        $projectPathsLeft = array_flip($this->projectPaths);

        /**
         * @var mixed $index
         * @var mixed $item
         */
        foreach ($config['updates'] as $index => $item) {
            if (!is_array($item)) {
                $this->output->invalid('This "updates" entry is not an array, received: ' . json_encode($item, JSON_PRETTY_PRINT));
                return 1;
            }

            if (!is_int($index)) {
                $this->output->invalid('This "updates" entry has a non-int key, received: ' . (string)$index);
                return 1;
            }

            if (!isset($item['directory']) || !is_string($item['directory'])) {
                $this->output->invalid('This "updates" entry does not have a valid "directory" entry, entry index: ' . $index);
                return 1;
            }

            if (!isset($projectPathsLeft[$item['directory']])) {
                $this->output->invalid('This "updates" entry does not correspond to a project, directory: ' . $item['directory']);
                return 1;
            }

            unset($projectPathsLeft[$item['directory']]);
        }

        if (count($projectPathsLeft) > 0) {
            $this->output->invalid('Some projects are missing "updates" entries: ' . json_encode($projectPathsLeft, JSON_PRETTY_PRINT));
            return 1;
        }

        return 0;
    }
}
