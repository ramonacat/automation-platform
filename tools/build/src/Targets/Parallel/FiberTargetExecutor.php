<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets\Parallel;

use function count;
use Exception;
use Fiber;
use function get_class;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\CollectedTargetOutput;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetExecutionSpy;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class FiberTargetExecutor
{
    /**
     * @var array<string, Fiber>
     */
    private array $runningFibers = [];

    /**
     * @var array<string, TargetOutput>
     */
    private array $outputsForRunningTargets = [];

    /**
     * @var array<string, array{0:BuildResult,1:CollectedTargetOutput}>
     */
    private array $results = [];

    public function __construct(
        private int $maxDegreeOfParallelism,
        private Context $context,
        private LoggerInterface $logger,
        private TargetExecutionSpy $spy
    ) {
    }

    /**
     * @param list<Artifact> $artifacts
     */
    public function addTargetFromCache(TargetId $targetId, array $artifacts): void
    {
        foreach ($artifacts as $artifact) {
            $this->context->artifactCollector()->collect($targetId, $artifact);
        }
        $this->results[$targetId->toString()] = [BuildResult::okCached($artifacts), new CollectedTargetOutput('', '')];
    }

    public function addTarget(Target $target, TargetOutput $output, Context $context): void
    {
        foreach ($target->dependencies() as $dependency) {
            if (!isset($this->results[$dependency->toString()])) {
                $this->waitFor($dependency);
            }

            if (!$this->results[$dependency->toString()][0]->hasSucceeded()) {
                $result = BuildResult::dependencyFailed($dependency);
                $collectedOutput = $output->finalize($result);
                $this->results[$target->id()->toString()] = [$result, $collectedOutput];
                return;
            }
        }

        $fiber = new Fiber(function () use ($target, $output, $context) {
            try {
                $this->spy->targetStarted($target->id(), $target->dependencies());
                $result = $target->execute($output, $context, $target->id()->path());
            } catch (Exception $e) {
                // NOTE: The details here are hidden on purpose, because the result will appear in the output from the build which is public
                $result = BuildResult::fail('Action failed. Uncaught exception of type: ' . get_class($e));

                $this->logger->error('Action failed. Uncaught exception.', ['exception' => $e]);
            } finally {
                $this->spy->targetFinished($target->id(), $result ?? BuildResult::fail('Action failed'));
            }
            Fiber::suspend($result);
        });

        $this->logger->info('Started executing target', [$target->id()->toString()]);

        $this->runningFibers[$target->id()->toString()] = $fiber;
        $this->outputsForRunningTargets[$target->id()->toString()] = $output;

        while (count($this->runningFibers) >= $this->maxDegreeOfParallelism) {
            $this->waitForAny();
        }
    }

    private function waitForAny(): void
    {
        while (count($this->runningFibers) > 0) {
            foreach ($this->runningFibers as $fiberTargetId => $fiber) {
                if ($fiber->isStarted()) {
                    /** @var BuildResult|null $result */
                    $result = $fiber->resume();
                } else {
                    /** @var BuildResult|null $result */
                    $result = $fiber->start();
                }

                if ($result !== null) {
                    $output = $this->outputsForRunningTargets[$fiberTargetId];
                    $collectedOutput = $output->finalize($result);
                    $this->results[$fiberTargetId] = [$result, $collectedOutput];
                    unset($this->runningFibers[$fiberTargetId], $this->outputsForRunningTargets[$fiberTargetId]);
                    foreach ($result->artifacts() as $artifact) {
                        $this->context->artifactCollector()->collect(TargetId::fromString($fiberTargetId), $artifact);
                    }

                    $this->logger->info(
                        'Target execution finished',
                        [
                            'targetId' => $fiberTargetId,
                            'stdout' => $collectedOutput->standardOutput(),
                            'stderr' => $collectedOutput->standardError()
                        ]
                    );

                    return;
                }
            }
        }
    }

    private function waitFor(TargetId $targetId): void
    {
        while (!isset($this->results[$targetId->toString()])) {
            $this->waitForAny();
        }
    }

    /**
     * @return array<string, array{0:BuildResult,1:CollectedTargetOutput}>
     */
    public function waitForAll(): array
    {
        while (count($this->runningFibers) > 0) {
            $this->waitForAny();
        }

        return $this->results;
    }
}
