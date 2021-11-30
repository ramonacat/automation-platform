<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Commands;

use function assert;
use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use Bramus\Ansi\Writers\StreamWriter;
use function count;
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
use Ramona\AutomationPlatformLibBuild\Artifacts\LogOnlyPublisher;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\BuildOutput\StyledBuildOutput;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Configuration\Locator;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Definition\BuildExecutor;
use Ramona\AutomationPlatformLibBuild\Definition\DefaultBuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Log\LogFormatter;
use Ramona\AutomationPlatformLibBuild\MachineInfo;
use Ramona\AutomationPlatformLibBuild\Targets\TargetDoesNotExist;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use function Safe\getcwd;
use function Safe\realpath;
use function sprintf;
use function str_replace;
use function uniqid;

final class Build
{
    private BuildFacts $buildFacts;
    private string $workingDirectory;
    private Ansi $ansi;
    private MachineInfo $machineInfo;

    public function __construct()
    {
        $this->machineInfo = new MachineInfo();

        $this->buildFacts = new BuildFacts(
            // todo use something like git tag as the ID
            str_replace('.', '', uniqid('', true)),
            getenv('CI') !== false,
            $this->machineInfo->logicalCores(),
            $this->machineInfo->physicalCores(),
        );

        $this->workingDirectory = realpath(getcwd());
        $this->ansi = new Ansi(new StreamWriter('php://stdout'));
    }

    /**
     * @psalm-param array<string, false|list<mixed>|string> $options
     * @psalm-param list<string> $arguments
     */
    public function __invoke(string $executableName, array $options, array $arguments): int
    {
        $configurationLocator = new Locator();
        $configuration = Configuration::fromFile($configurationLocator->locateConfigurationFile());

        $subtype = $options['environment'] ?? 'dev';
        assert(is_string($subtype));
        $environmentConfigurationFile = $configurationLocator->tryLocateConfigurationFile($subtype);
        if ($environmentConfigurationFile !== null) {
            $configuration = $configuration->merge(Configuration::fromFile($environmentConfigurationFile));
        }

        $buildDefinitionsLoader = new DefaultBuildDefinitionsLoader($this->buildFacts, $configuration);

        if (count($arguments) !== 1) {
            $this->printUsage($executableName, $buildDefinitionsLoader);

            return 1;
        }

        $buildExecutor = new BuildExecutor(
            $this->createFileLogger(),
            new StyledBuildOutput($this->ansi),
            $buildDefinitionsLoader,
            $configuration,
            $this->buildFacts,
        );

        $this->printBuildFacts();

        try {
            $result = $buildExecutor->executeTarget(new TargetId(getcwd(), $arguments[0]));
        } catch (TargetDoesNotExist $exception) {
            $this
                ->ansi
                ->text(sprintf('The target "%s" does not exist', $exception->targetId()->toString()));
            return 1;
        } catch (Exception $e) {
            $this->printException($e);

            return 1;
        }

        if (!$result->hasSucceeded()) {
            $this
                ->ansi
                ->text('The build has failed.' . PHP_EOL)
                ->text($result->message() ?? '<no message>');

            return 1;
        }

        $this->publishArtifacts($result->artifacts());
        return 0;
    }

    private function createFileLogger(): LoggerInterface
    {
        $logger = new Logger('ap-build');
        $logHandler = new StreamHandler($this->workingDirectory . '/build.log');
        $logHandler->setFormatter(new LogFormatter($this->buildFacts));
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

    private function printException(Exception $e): void
    {
        if ($this->buildFacts->inPipeline()) {
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
            ->text(sprintf('Supported actions: %s%s', implode(', ', $buildDefinitionsLoader->getActionNames($this->workingDirectory)), PHP_EOL));
    }

    private function printBuildFacts(): void
    {
        $this
            ->ansi
            ->color([SGR::COLOR_FG_CYAN_BRIGHT])
            ->text('Build facts:')
            ->text(PHP_EOL)
            ->nostyle()
            ->text('    CI: ')
            ->text($this->buildFacts->inPipeline() ? '✔' : '❌')
            ->text(PHP_EOL)
            ->text('    Build ID: ')
            ->text($this->buildFacts->buildId())
            ->text(PHP_EOL)
            ->text('    Physical cores: ')
            ->text((string)$this->buildFacts->physicalCores())
            ->text(PHP_EOL)
            ->text('    Logical cores: ')
            ->text((string)$this->buildFacts->logicalCores())
            ->text(PHP_EOL);
    }
}
