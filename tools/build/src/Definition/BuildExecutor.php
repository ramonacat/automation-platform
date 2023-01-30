<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Definition;

use function array_map;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\ChangeTracking\ChangeTracker;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Output\BuildOutput;
use Ramona\AutomationPlatformLibBuild\Queue\Builder;
use Ramona\AutomationPlatformLibBuild\State\State;
use Ramona\AutomationPlatformLibBuild\Targets\LoggingTargetExecutionSpy;
use Ramona\AutomationPlatformLibBuild\Targets\Parallel\FiberTargetExecutor;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class BuildExecutor
{
    public function __construct(
        private LoggerInterface        $logger,
        private BuildOutput            $buildOutput,
        private BuildDefinitionsLoader $buildDefinitions,
        private BuildFacts             $buildFacts,
        private State                  $state,
        private ChangeTracker          $changeTracker,
        private Builder                $queueBuilder
    ) {
    }

    public function executeTarget(TargetId $targetId, Context $context): BuildResult
    {
        $currentStateId = $this->changeTracker->getCurrentStateId();
        $queue = $this->queueBuilder->build($targetId);

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
            $context,
            $this->logger,
            new LoggingTargetExecutionSpy($targetId->path() . '/build-times.log'),
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
                    $targetFiberStack->addTarget($target, $this->buildOutput->startTarget($targetId), $context);
                }
            } else {
                $cacheBusters[$targetId->toString()] = true;
                $targetFiberStack->addTarget($target, $this->buildOutput->startTarget($targetId), $context);
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
            : BuildResult::ok($context->artifactCollector()->all());
    }
}
