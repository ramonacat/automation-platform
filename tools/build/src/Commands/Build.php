<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Commands;

use function assert;
use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use Bramus\Ansi\Writers\StreamWriter;
use function count;
use function dirname;
use Exception;
use function get_class;
use function getenv;
use function implode;
use function is_string;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use const PHP_EOL;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\Artifacts\LogOnlyPublisher;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\ChangeTracking\GitChangeTracker;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Configuration\Locator;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Definition\BuildExecutor;
use Ramona\AutomationPlatformLibBuild\Definition\DefaultBuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Log\LogFormatter;
use Ramona\AutomationPlatformLibBuild\MachineInfo;
use Ramona\AutomationPlatformLibBuild\Output\StyledBuildOutput;
use Ramona\AutomationPlatformLibBuild\Queue\Builder;
use Ramona\AutomationPlatformLibBuild\State\DotBuildStateStorage;
use Ramona\AutomationPlatformLibBuild\Targets\TargetDoesNotExist;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use function Safe\getcwd;
use function Safe\json_encode;
use function Safe\realpath;
use function sprintf;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Webmozart\Assert\Assert;

final class Build
{
    private string $workingDirectory;
    private Ansi $ansi;

    public function __construct()
    {
        $this->workingDirectory = realpath(getcwd());
        $this->ansi = new Ansi(new StreamWriter('php://stdout'));
    }

    /**
     * @psalm-param array<string, false|list<mixed>|string> $options
     * @psalm-param list<string> $arguments
     */
    public function __invoke(string $executableName, array $options, array $arguments): int
    {
        $inPipeline = getenv('CI') !== false;

        $logger = $this->createFileLogger($inPipeline);

        $machineInfo = new MachineInfo();
        $changeTracker = new GitChangeTracker($logger, $this->ansi);

        try {
            $stateId = $changeTracker->getCurrentStateId();
        } catch (ProcessFailedException $e) {
            $this
                ->ansi
                ->color([SGR::COLOR_FG_RED])
                ->text('Failed to get current state ID: ')
                ->nostyle()
            ;
            $this->ansi->text((string)$e . PHP_EOL . PHP_EOL);

            throw $e;
        }

        $buildFacts = new BuildFacts(
            $stateId,
            $inPipeline,
            $machineInfo->logicalCores(),
            $machineInfo->physicalCores(),
        );

        $environment = $options['environment'] ?? 'dev';
        assert(is_string($environment));

        $configuration = $this->loadConfiguration($environment);

        /** @var mixed $rootPath */
        $rootPath = $configuration->getSingleBuildValue('$.root-path');
        Assert::string($rootPath);
        $stateStorage = new DotBuildStateStorage($rootPath);
        $state = $stateStorage->get();

        $buildDefinitionsLoader = new DefaultBuildDefinitionsLoader($buildFacts, $configuration, $this->ansi);

        if (count($arguments) !== 1) {
            $this->printUsage($executableName, $buildDefinitionsLoader);

            return 1;
        }

        $buildExecutor = new BuildExecutor(
            $logger,
            new StyledBuildOutput($this->ansi),
            $buildDefinitionsLoader,
            $buildFacts,
            $state,
            $changeTracker,
            new Builder($buildDefinitionsLoader)
        );

        $this->printBuildFacts($buildFacts);

        try {
            $result = $buildExecutor->executeTarget(new TargetId(getcwd(), $arguments[0]), new Context($configuration, new Collector(), $buildFacts));
        } catch (TargetDoesNotExist $exception) {
            $this
                ->ansi
                ->text(sprintf('The target "%s" does not exist', $exception->targetId()->toString()));
            return 1;
        } catch (Exception $e) {
            $this->printException($buildFacts, $e);

            return 1;
        }

        if (!$result->hasSucceeded()) {
            $this
                ->ansi
                ->text('The build has failed.' . PHP_EOL)
                ->text($result->message() ?? '<no message>');

            return 1;
        }

        $stateStorage->set($state);
        $this->publishArtifacts($result->artifacts());
        return 0;
    }

    private function createFileLogger(bool $inPipeline): LoggerInterface
    {
        $logger = new Logger('ap-build');
        $logHandler = new StreamHandler($this->workingDirectory . '/build.log');
        $logHandler->setFormatter(new LogFormatter($inPipeline));
        $logger->pushHandler($logHandler);
        return $logger;
    }

    /**
     * @param list<Artifact> $artifacts
     */
    private function publishArtifacts(array $artifacts): void
    {
        $artifactPublisher = new LogOnlyPublisher($this->ansi);
        foreach ($artifacts as $artifact) {
            $artifactPublisher->publish($artifact);
        }

        $artifactPublisher->print();
    }

    private function printException(BuildFacts $buildFacts, Exception $e): void
    {
        if ($buildFacts->inPipeline()) {
            $this
                ->ansi
                ->text(sprintf('Unhandled exception of type: %s' . PHP_EOL, get_class($e)))
                ->text('Running in CI, will not print the exception details.' . PHP_EOL);
        } else {
            $this
                ->ansi
                ->text(sprintf('%s', (string)$e . PHP_EOL));
        }
    }

    private function printUsage(string $executableName, BuildDefinitionsLoader $buildDefinitionsLoader): void
    {
        $this
            ->ansi
            ->text(sprintf('Usage: %s [action-name]%s', $executableName, PHP_EOL))
            ->text(sprintf('Supported actions: %s%s', implode(', ', $buildDefinitionsLoader->targetNames($this->workingDirectory)), PHP_EOL));
    }

    private function printBuildFacts(BuildFacts $buildFacts): void
    {
        $this
            ->ansi
            ->color([SGR::COLOR_FG_CYAN_BRIGHT])
            ->text('Build facts:')
            ->text(PHP_EOL)
            ->nostyle()
            ->text('    CI: ')
            ->text($buildFacts->inPipeline() ? '✔' : '❌')
            ->text(PHP_EOL)
            ->text('    Build ID: ')
            ->text($buildFacts->buildId())
            ->text(PHP_EOL)
            ->text('    Physical cores: ')
            ->text((string)$buildFacts->physicalCores())
            ->text(PHP_EOL)
            ->text('    Logical cores: ')
            ->text((string)$buildFacts->logicalCores())
            ->text(PHP_EOL);
    }

    private function loadConfiguration(string $environment): Configuration
    {
        $configurationLocator = new Locator();
        $configurationFilePath = $configurationLocator->locateConfigurationFile();
        $rootPath = dirname($configurationFilePath);
        $defaultConfiguration = Configuration::fromJsonString(json_encode(['build' => ['root-path' => $rootPath]]));
        $configuration = Configuration::fromFile($configurationFilePath);
        $configuration = $defaultConfiguration->merge($configuration);

        $optionalAdditionalConfigurations = ['local', $environment, $environment . '.local'];

        foreach ($optionalAdditionalConfigurations as $subtype) {
            $additionalConfiguration = $configurationLocator->tryLocateConfigurationFile($subtype);
            if ($additionalConfiguration !== null) {
                $configuration = $configuration->merge(Configuration::fromFile($additionalConfiguration));
            }
        }

        return $configuration;
    }
}
