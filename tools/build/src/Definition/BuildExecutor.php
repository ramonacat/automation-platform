<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function array_map;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Artifacts\Collector;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\BuildOutput\BuildOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\ChangeTracking\ChangeTracker;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\State\State;
use Ramona\AutomationPlatformLibBuild\Targets\Parallel\FiberTargetExecutor;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\Targets\TargetQueue;

final class BuildExecutor
{
    private Collector $artifactCollector;

    public function __construct(
        private LoggerInterface        $logger,
        private BuildOutput            $buildOutput,
        private BuildDefinitionsLoader $buildDefinitions,
        private Configuration          $configuration,
        private BuildFacts             $buildFacts,
        private State                  $state,
        private ChangeTracker          $changeTracker
    ) {
        $this->artifactCollector = new Collector();
    }

    /**
     * @todo extract this to its own class?
     */
    public function buildQueue(TargetId $desiredTarget): TargetQueue
    {
        $leftToBuild = new TargetQueue();
        $leftToBuild->enqueue($desiredTarget);

        $buildQueue = new TargetQueue();

        while (!$leftToBuild->isEmpty()) {
            $targetId = $leftToBuild->dequeue();
            $dependencies = $this->buildDefinitions->target($targetId)->dependencies();

            $allDependenciesQueued = true;

            foreach ($dependencies as $targetDependency) {
                $isInBuildQueue = $buildQueue->hasId($targetDependency);

                if (!$isInBuildQueue) {
                    if (!$leftToBuild->hasId($targetDependency)) {
                        $leftToBuild->enqueue($targetDependency);
                    }

                    $allDependenciesQueued = false;
                }
            }

            if ($allDependenciesQueued) {
                $buildQueue->enqueue($targetId);
            } else {
                $leftToBuild->enqueue($targetId);
            }
        }

        return $buildQueue;
    }

    public function executeTarget(TargetId $targetId): BuildResult
    {
        $context = new Context(
            $this->configuration,
            $this->artifactCollector,
            $this->buildFacts
        );

        $currentStateId = $this->changeTracker->getCurrentStateId();
        $queue = $this->buildQueue($targetId);

        $this->logger->info(
            'Starting a build',
            [
                'targetId' => $targetId->toString(),
                'queue' => array_map(
                    static fn (TargetId $t) => $t->toString(),
                    $queue->asArray()
                )
            ]
        );

        $targetFiberStack = new FiberTargetExecutor(
            $this->buildFacts->logicalCores(),
            $this->artifactCollector,
            $this->logger
        );

        $cacheBusters = [];

        while (!$queue->isEmpty()) {
            $targetId = $queue->dequeue();
            $target = $this->buildDefinitions->target($targetId);

            $state = $this->state->getStateIdForTarget($targetId);

            if (
                $state !== null
                && !$this->changeTracker->wasModifiedSince($state[0], $targetId->path())
            ) {
                foreach ($target->dependencies() as $dependency) {
                    if (isset($cacheBusters[$dependency->toString()])) {
                        $cacheBusters[$targetId->toString()] = true;
                        break;
                    }
                }

                if (!isset($cacheBusters[$targetId->toString()])) {
                    $this->logger->info('Adding target from cache', ['id' => $targetId->toString()]);
                    $targetFiberStack->addTargetFromCache($targetId, $state[1]);
                } else {
                    $targetFiberStack->addTarget($targetId, $target, $this->buildOutput->startTarget($targetId), $context);
                }
            } else {
                $cacheBusters[$targetId->toString()] = true;
                $targetFiberStack->addTarget($targetId, $target, $this->buildOutput->startTarget($targetId), $context);
            }
        }

        $results = $targetFiberStack->waitForAll();

        $this->buildOutput->finalizeBuild($results);

        $failed = false;
        foreach ($results as $resultTargetId => $result) {
            if (!$result[0]->hasSucceeded()) {
                $failed = true;
            } else {
                $this->state->setTargetStateId(TargetId::fromString($resultTargetId), $currentStateId, $result[0]->artifacts());
            }
        }

        return $failed
            ? BuildResult::fail('Build failed')
            : BuildResult::ok($this->artifactCollector->all());
    }
}
