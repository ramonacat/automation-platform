<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use function array_reverse;
use function array_unshift;
use function is_array;
use Remorhaz\JSON\Data\Value\EncodedJson\NodeValueFactory;
use Remorhaz\JSON\Data\Value\NodeValueInterface;
use Remorhaz\JSON\Path\Processor\Processor;
use Remorhaz\JSON\Path\Processor\ProcessorInterface;
use Remorhaz\JSON\Path\Query\QueryFactory;
use Remorhaz\JSON\Path\Query\QueryFactoryInterface;
use function Safe\file_get_contents;
use stdClass;

final class Configuration
{
    private QueryFactoryInterface $queryFactory;
    /** @var list<NodeValueInterface> */
    private array $configurations;
    private ProcessorInterface $processor;

    private function __construct(string $jsonString)
    {
        $this->queryFactory = QueryFactory::create();
        $this->configurations = [NodeValueFactory::create()->createValue($jsonString)];
        $this->processor = Processor::create();
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
        $query = $this->queryFactory->createQuery($jsonPath);

        $buildConfigurationQuery = $this->queryFactory->createQuery('$.build');

        $buildConfigurations = [];
        foreach ($this->configurations as $configuration) {
            $buildConfiguration = $this->processor->selectOne($buildConfigurationQuery, $configuration);

            if ($buildConfiguration->exists()) {
                $buildConfigurations[] = $buildConfiguration->get();
            }
        }

        if ($buildConfigurations === []) {
            throw InvalidConfiguration::missingBuildKey();
        }

        foreach ($buildConfigurations as $buildConfiguration) {
            if (!$buildConfiguration instanceof NodeValueInterface) {
                throw InvalidConfiguration::buildNotANode();
            }
            $result = $this->processor->selectOne($query, $buildConfiguration);

            if (!$result->exists()) {
                continue;
            }

            return $result->decode();
        }

        throw ConfigurationValueNotFound::forPath($jsonPath);
    }

    public function getRuntimeConfiguration(): mixed
    {
        $query = $this->queryFactory->createQuery('$.runtime');

        /** @var list<array<mixed>> $allConfigurations */
        $allConfigurations = [];

        foreach ($this->configurations as $configuration) {
            $runtimeConfiguration = $this->processor->selectOne($query, $configuration);

            if (!$runtimeConfiguration->exists()) {
                continue;
            }

            /** @var stdClass $decoded */
            $decoded = $runtimeConfiguration->decode();
            $allConfigurations[] = $this->castToArrayRecursively($decoded);
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

    private function castToArrayRecursively(stdClass $in): array
    {
        $out = (array)$in;

        /** @var mixed $value */
        foreach ($out as $key => $value) {
            if ($value instanceof stdClass) {
                $out[$key] = $this->castToArrayRecursively($value);
            }
        }

        return $out;
    }

    public function merge(Configuration $other): self
    {
        $result = clone $this;

        foreach ($other->configurations as $configuration) {
            array_unshift($result->configurations, $configuration);
        }

        return $result;
    }
}
