<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Commands;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\Writers\StreamWriter;
use function count;
use Exception;
use function get_class;
use function getenv;
use function implode;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use const PHP_EOL;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Artifacts\LogOnlyPublisher;
use Ramona\AutomationPlatformLibBuild\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\BuildExecutor;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\BuildOutput\CIBuildOutput;
use Ramona\AutomationPlatformLibBuild\BuildOutput\StyledBuildOutput;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Configuration\Locator;
use Ramona\AutomationPlatformLibBuild\DefaultBuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Log\LogFormatter;
use Ramona\AutomationPlatformLibBuild\TargetDoesNotExist;
use Ramona\AutomationPlatformLibBuild\TargetId;
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
    private BuildDefinitionsLoader $buildDefinitionsLoader;
    private BuildExecutor $buildExecutor;

    public function __construct()
    {
        $this->buildFacts = new BuildFacts(
            // todo use something like git tag as the ID
            str_replace('.', '', uniqid('', true)),
            getenv('CI') !== false
        );

        $this->workingDirectory = realpath(getcwd());
        $this->ansi = new Ansi(new StreamWriter('php://stdout'));
        $this->buildDefinitionsLoader = new DefaultBuildDefinitionsLoader();

        $this->buildExecutor = new BuildExecutor(
            $this->createFileLogger(),
            $this->buildFacts->inPipeline() ? new CIBuildOutput($this->ansi) : new StyledBuildOutput($this->ansi),
            $this->buildDefinitionsLoader,
            Configuration::fromFile((new Locator())->locateConfigurationFile()),
            $this->buildFacts,
        );
    }

    /**
     * @psalm-param list<string> $arguments
     */
    public function __invoke(array $arguments): int
    {
        if (count($arguments) !== 2) {
            $this->printUsage($arguments[0]);

            return 1;
        }

        try {
            $result = $this->buildExecutor->executeTarget(new TargetId(getcwd(), $arguments[1]));
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
                ->text($result->getMessage() ?? '<no message>');

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

    private function printUsage(string $executableName): void
    {
        $this
            ->ansi
            ->text(sprintf('Usage: %s [action-name]%s', $executableName, PHP_EOL))
            ->text(sprintf('Supported actions: %s%s', implode(', ', $this->buildDefinitionsLoader->getActionNames($this->workingDirectory)), PHP_EOL));
    }
}
