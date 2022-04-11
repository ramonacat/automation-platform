<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use function array_reverse;
use function array_unshift;
use function is_array;
use JsonPath\JsonObject;
use function Safe\file_get_contents;
use function Safe\json_decode;

final class Configuration
{
    /** @var list<array> */
    private array $configurations;

    private function __construct(string $jsonString)
    {
        $configuration = json_decode($jsonString, true);
        if (!is_array($configuration)) {
            throw InvalidConfiguration::notAnArray();
        }
        $this->configurations = [$configuration];
    }

    public static function fromFile(string $path): self
    {
        $contents = file_get_contents($path);

        return new self($contents);
    }

    public static function fromJsonString(string $json): self
    {
        return new self($json);
    }

    public function getSingleBuildValue(string $jsonPath): mixed
    {
        /** @var mixed|null $value */
        $value = $this->getSingleBuildValueOrDefault($jsonPath, null);

        if ($value === null) {
            throw ConfigurationValueNotFound::forPath($jsonPath);
        }

        return $value;
    }

    public function getRuntimeConfiguration(): mixed
    {
        /** @var list<array<mixed>> $allConfigurations */
        $allConfigurations = [];

        foreach ($this->configurations as $configuration) {
            if (!isset($configuration['runtime'])) {
                continue;
            }

            if (!is_array($configuration['runtime'])) {
                throw InvalidConfiguration::runtimeNotAnArray();
            }

            $allConfigurations[] = $configuration['runtime'];
        }

        return $this->mergeRuntimeConfigurations(array_reverse($allConfigurations));
    }

    /**
     * @param list<array<array-key, mixed>> $allConfigurations
     * @return array<array-key, mixed>
     */
    private function mergeRuntimeConfigurations(array $allConfigurations): array
    {
        /** @psalm-var array<array-key, mixed> $finalConfiguration */
        $finalConfiguration = [];

        foreach ($allConfigurations as $configuration) {
            /** @psalm-var mixed $value */
            foreach ($configuration as $key => $value) {
                if (isset($finalConfiguration[$key]) && is_array($finalConfiguration[$key]) && is_array($value)) {
                    $finalConfiguration[$key] = $this->mergeRuntimeConfigurations([$finalConfiguration[$key], $value]);
                } else {
                    /** @psalm-suppress MixedAssignment */
                    $finalConfiguration[$key] = $value;
                }
            }
        }

        return $finalConfiguration;
    }

    public function merge(Configuration $other): self
    {
        $result = clone $this;

        foreach ($other->configurations as $configuration) {
            array_unshift($result->configurations, $configuration);
        }

        return $result;
    }

    public function getSingleBuildValueOrDefault(string $jsonPath, mixed $default = null): mixed
    {
        $buildConfigurations = [];
        foreach ($this->configurations as $configuration) {
            if (isset($configuration['build'])) {
                if (!is_array($configuration['build'])) {
                    throw InvalidConfiguration::buildNotAnArray();
                }

                $buildConfigurations[] = $configuration['build'];
            }
        }

        if ($buildConfigurations === []) {
            throw InvalidConfiguration::missingBuildKey();
        }

        foreach ($buildConfigurations as $buildConfiguration) {
            $jsonObject = new JsonObject($buildConfiguration, true);
            /** @var mixed|false $result */
            $result = $jsonObject->get($jsonPath);

            if ($result === false) {
                continue;
            }

            return $result;
        }

        return $default;
    }
}
