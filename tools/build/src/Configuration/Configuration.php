<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Configuration;

use Remorhaz\JSON\Data\Value\EncodedJson\NodeValueFactory;
use Remorhaz\JSON\Data\Value\NodeValueInterface;
use Remorhaz\JSON\Path\Processor\Processor;
use Remorhaz\JSON\Path\Processor\ProcessorInterface;
use Remorhaz\JSON\Path\Query\QueryFactory;
use Remorhaz\JSON\Path\Query\QueryFactoryInterface;
use function Safe\file_get_contents;

final class Configuration
{
    private QueryFactoryInterface $queryFactory;
    private NodeValueInterface $configuration;
    private ProcessorInterface $processor;

    private function __construct(string $jsonString)
    {
        $this->queryFactory = QueryFactory::create();
        $this->configuration = NodeValueFactory::create()->createValue($jsonString);
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
        $buildConfiguration = $this->processor->selectOne($buildConfigurationQuery, $this->configuration);

        if (!$buildConfiguration->exists()) {
            throw InvalidConfiguration::missingBuildKey();
        }

        $rootBuildConfiguration = $buildConfiguration->get();
        if (!$rootBuildConfiguration instanceof NodeValueInterface) {
            throw InvalidConfiguration::buildNotANode();
        }
        $result = $this->processor->selectOne($query, $rootBuildConfiguration);

        if (!$result->exists()) {
            throw ConfigurationValueNotFound::forPath($jsonPath);
        }

        return $result->decode();
    }

    public function getRuntimeConfiguration(): mixed
    {
        $query = $this->queryFactory->createQuery('$.runtime');

        $runtimeConfiguration = $this->processor->selectOne($query, $this->configuration);

        if (!$runtimeConfiguration->exists()) {
            throw InvalidConfiguration::missingRuntimeKey();
        }

        return $runtimeConfiguration->decode();
    }
}
