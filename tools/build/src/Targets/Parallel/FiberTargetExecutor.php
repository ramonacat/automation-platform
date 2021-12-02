<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Targets\Parallel;

use function count;
use Exception;
use Fiber;
use function get_class;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildOutput\NullTargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
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
     * @var array<string, array{0:BuildResult,1:TargetOutput}>
     */
    private array $results = [];

    public function __construct(
        private int $maxDegreeOfParallelism,
        private Collector $artifactCollector,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<Artifact> $artifacts
     */
    public function addTargetFromCache(TargetId $targetId, array $artifacts): void
    {
        // fixme separate BuildResult kind for cached
        foreach ($artifacts as $artifact) {
            $this->artifactCollector->collect($targetId, $artifact);
        }
        $this->results[$targetId->toString()] = [BuildResult::okCached($artifacts), new NullTargetOutput()];
    }

    // todo remove the TargetId argument, it's a part of Target anyway
    public function addTarget(TargetId $targetId, Target $target, TargetOutput $output, Context $context): void
    {
        foreach ($target->dependencies() as $dependency) {
            if (!isset($this->results[$dependency->toString()])) {
                $this->waitFor($dependency);
            }

            if (!$this->results[$dependency->toString()][0]->hasSucceeded()) {
                $result = BuildResult::dependencyFailed($dependency);
                $this->results[$targetId->toString()] = [$result, $output];
                $output->finalize($result);
                return;
            }
        }

        $fiber = new Fiber(function () use ($target, $targetId, $output, $context) {
            try {
                $result = $target->execute($output, $context, $targetId->path());
            } catch (Exception $e) {
                // NOTE: The details here are hidden on purpose, because the result will appear in the output from the build which is public
                $result = BuildResult::fail('Action failed. Uncaught exception of type: ' . get_class($e));

                $this->logger->error('Action failed. Uncaught exception.', ['exception' => $e]);
            }
            Fiber::suspend($result);
        });

        $this->logger->info('Started executing target', [$targetId->toString()]);

        $this->runningFibers[$targetId->toString()] = $fiber;
        $this->outputsForRunningTargets[$targetId->toString()] = $output;

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
                    $this->results[$fiberTargetId] = [$result, $output];
                    unset($this->runningFibers[$fiberTargetId]);
                    $output->finalize($result);
                    unset($this->outputsForRunningTargets[$fiberTargetId]);
                    foreach ($result->artifacts() as $artifact) {
                        $this->artifactCollector->collect(TargetId::fromString($fiberTargetId), $artifact);
                    }

                    $this->logger->info(
                        'Target execution finished',
                        [
                            'targetId' => $fiberTargetId,
                            'stdout' => $output->getCollectedStandardOutput(),
                            'stderr' => $output->getCollectedStandardError()
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
     * @return array<string, array{0:BuildResult,1:TargetOutput}>
     */
    public function waitForAll(): array
    {
        while (count($this->runningFibers) > 0) {
            $this->waitForAny();
        }

        return $this->results;
    }
}
