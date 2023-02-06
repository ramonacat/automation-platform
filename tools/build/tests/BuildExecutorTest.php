<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildFacts;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\ChangeTracking\ChangeTracker;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Definition\BuildExecutor;
use Ramona\AutomationPlatformLibBuild\Output\BuildOutput;
use Ramona\AutomationPlatformLibBuild\Output\CollectedTargetOutput;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Queue\Builder;
use Ramona\AutomationPlatformLibBuild\State\State;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use RuntimeException;
use function Safe\realpath;
use function sprintf;
use Tests\Ramona\AutomationPlatformLibBuild\Actions\ContextFactory;

final class BuildExecutorTest extends TestCase
{
    /**
     * @var BuildDefinitionsLoader&MockObject
     */
    private BuildDefinitionsLoader $buildDefinitionsLoader;
    /**
     * @var BuildOutput&MockObject
     */
    private BuildOutput $buildOutput;
    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $logger;
    private BuildExecutor $buildExecutor;
    private State $state;
    /** @var ChangeTracker&MockObject  */
    private ChangeTracker $changeTracker;

    public function setUp(): void
    {
        $this->buildDefinitionsLoader = $this->createMock(BuildDefinitionsLoader::class);
        $this->buildOutput = $this->createMock(BuildOutput::class);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->changeTracker = $this->createMock(ChangeTracker::class);
        $this->state = new State();
        $this->buildExecutor = new BuildExecutor(
            $this->logger,
            $this->buildOutput,
            $this->buildDefinitionsLoader,
            new BuildFacts('test', null, 1, 1),
            $this->state,
            $this->changeTracker,
            new Builder($this->buildDefinitionsLoader)
        );
    }

    public function testCanExecuteATarget(): void
    {
        $this->initDefaultTargetOutput();

        $targetId = new TargetId(__DIR__ . '/fixtures/c', 'check');
        $action = $this->createMock(BuildAction::class);

        $action->expects(self::once())->method('execute')->willReturn(BuildResult::ok([]));

        $this->setupDefinitions([
            new Target($targetId, $action),
        ]);

        $this->buildExecutor->executeTarget($targetId, ContextFactory::create());
    }

    public function testWillStartEachTarget(): void
    {
        $this->initDefaultTargetOutput();

        $targetIdA = new TargetId(__DIR__ . '/fixtures/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/fixtures/c', 'b');

        $this->setupDefinitions([
            new Target($targetIdA, new NoOp()),
            new Target($targetIdB, new NoOp(), [$targetIdA]),
        ]);

        $this
            ->buildOutput
            ->expects(self::exactly(2))
            ->method('startTarget')
            ->withConsecutive([$targetIdA], [$targetIdB]);

        $this->buildExecutor->executeTarget($targetIdB, ContextFactory::create());
    }

    public function testWillCollectAllArtifacts(): void
    {
        $this->initDefaultTargetOutput();

        $artifactA = new ContainerImage('ramona-test-1', 'ramona/test1', 'latest');
        $artifactB = new ContainerImage('ramona-test-2', 'ramona/test2', 'latest');

        $targetIdA = new TargetId(__DIR__ . '/fixtures/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/fixtures/c', 'b');

        $resultA = BuildResult::ok([$artifactA]);
        $resultB = BuildResult::ok([$artifactB]);

        $actionA = $this->createMock(BuildAction::class);
        $actionA
            ->method('execute')
            ->willReturn($resultA);
        $actionB = $this->createMock(BuildAction::class);
        $actionB
            ->method('execute')
            ->willReturn($resultB);

        $this->setupDefinitions([
            new Target($targetIdA, $actionA),
            new Target($targetIdB, $actionB, [$targetIdA]),
        ]);

        $result = $this->buildExecutor->executeTarget($targetIdB, ContextFactory::create());

        self::assertEquals([$artifactA, $artifactB], $result->artifacts());
    }

    public function testWillFinalizeTheBuild(): void
    {
        $targetIdA = new TargetId(__DIR__ . '/fixtures/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/fixtures/c', 'b');

        $resultA = BuildResult::ok([]);
        $resultB = BuildResult::fail('boo');

        $actionA = $this->createMock(BuildAction::class);
        $actionA
            ->method('execute')
            ->willReturn($resultA);
        $actionB = $this->createMock(BuildAction::class);
        $actionB
            ->method('execute')
            ->willReturn($resultB);

        $this->setupDefinitions([
            new Target($targetIdA, $actionA),
            new Target($targetIdB, $actionB, [$targetIdA]),
        ]);

        $targetOutputA = $this->createMock(TargetOutput::class);
        $collectedTargetOutputA = new CollectedTargetOutput('a', 'b');
        $targetOutputA->method('finalize')->willReturn($collectedTargetOutputA);
        $targetOutputB = $this->createMock(TargetOutput::class);
        $collectedTargetOutputB = new CollectedTargetOutput('c', 'd');
        $targetOutputB->method('finalize')->willReturn($collectedTargetOutputB);

        $this
            ->buildOutput
            ->method('startTarget')
            ->willReturnOnConsecutiveCalls(
                $targetOutputA,
                $targetOutputB
            );

        $this
            ->buildOutput
            ->expects(self::once())
            ->method('finalizeBuild')
            ->with([
                $targetIdA->toString() => [$resultA, $collectedTargetOutputA],
                $targetIdB->toString() => [$resultB, $collectedTargetOutputB],
            ]);

        $this->buildExecutor->executeTarget($targetIdB, ContextFactory::create());
    }

    public function testWillFailIfAnyTargetFailed(): void
    {
        $this->initDefaultTargetOutput();

        $targetIdA = new TargetId(__DIR__ . '/fixtures/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/fixtures/c', 'b');

        $resultA = BuildResult::ok([]);
        $resultB = BuildResult::fail('boo');

        $actionA = $this->createMock(BuildAction::class);
        $actionA
            ->method('execute')
            ->willReturn($resultA);
        $actionB = $this->createMock(BuildAction::class);
        $actionB
            ->method('execute')
            ->willReturn($resultB);

        $this->setupDefinitions([
            new Target($targetIdA, $actionA),
            new Target($targetIdB, $actionB, [$targetIdA]),
        ]);

        $result = $this->buildExecutor->executeTarget($targetIdB, ContextFactory::create());
        self::assertFalse($result->hasSucceeded());
    }

    public function testWillRebuildIFADeeplyNestedDependencyWasRebuilt(): void
    {
        $this->initDefaultTargetOutput();

        $targetIdC = new TargetId(__DIR__ . '/fixtures/c', 'c');
        $targetC = new Target($targetIdC, new NoOp());

        $targetIdB = new TargetId(__DIR__ . '/fixtures/b', 'b');
        $targetB = new Target($targetIdB, new NoOp(), [$targetIdC]);

        $actionA = $this->createMock(BuildAction::class);
        $actionA->expects(self::once())->method('execute')->willReturn(BuildResult::ok([]));
        $targetIdA = new TargetId(__DIR__ . '/fixtures/a', 'a');
        $targetA = new Target($targetIdA, $actionA, [$targetIdB]);

        $this
            ->changeTracker
            ->method('wasModifiedSince')
            ->willReturnCallback(function (string $_, string $directory) {
                return realpath($directory) === realpath(__DIR__ . '/fixtures/c');
            });

        $this->changeTracker->method('getCurrentStateId')->willReturn('aaa');

        $this->state->setTargetStateId($targetIdB, 'aaa', []);
        $this->state->setTargetStateId($targetIdA, 'aaa', []);

        $this->setupDefinitions([$targetB, $targetC, $targetA]);

        $this->buildExecutor->executeTarget($targetIdA, ContextFactory::create());
    }

    public function testWillRebuildIFADeeplyNestedDependencyHasNoStateId(): void
    {
        $this->initDefaultTargetOutput();

        $actionC = $this->createMock(BuildAction::class);
        $actionC->expects(self::once())->method('execute')->willReturn(BuildResult::ok([]));
        $targetIdC = new TargetId(__DIR__ . '/fixtures/c', 'c');
        $targetC = new Target($targetIdC, $actionC);

        $targetIdB = new TargetId(__DIR__ . '/fixtures/b', 'b');
        $targetB = new Target($targetIdB, new NoOp(), [$targetIdC]);

        $actionA = $this->createMock(BuildAction::class);
        $actionA->expects(self::once())->method('execute')->willReturn(BuildResult::ok([]));
        $targetIdA = new TargetId(__DIR__ . '/fixtures/a', 'a');
        $targetA = new Target($targetIdA, $actionA, [$targetIdB]);

        $this
            ->changeTracker
            ->method('wasModifiedSince')
            ->willReturnCallback(function (string $_, string $__) {
                return false;
            });

        $this->changeTracker->method('getCurrentStateId')->willReturn('aaa');

        $this->state->setTargetStateId($targetIdB, 'aaa', []);
        $this->state->setTargetStateId($targetIdA, 'aaa', []);

        $this->setupDefinitions([$targetB, $targetC, $targetA]);

        $this->buildExecutor->executeTarget($targetIdA, ContextFactory::create());
    }

    public function testWillNotRebuildIfNoDependenciesWereRebuilt(): void
    {
        $targetIdC = new TargetId(__DIR__ . '/fixtures/c', 'c');
        $targetC = new Target($targetIdC, new NoOp());

        $targetIdB = new TargetId(__DIR__ . '/fixtures/b', 'b');
        $targetB = new Target($targetIdB, new NoOp());

        $actionA = $this->createMock(BuildAction::class);
        $actionA->expects(self::never())->method('execute')->willReturn(BuildResult::ok([]));
        $targetIdA = new TargetId(__DIR__ . '/fixtures/a', 'a');
        $targetA = new Target($targetIdA, $actionA, [$targetIdB]);

        $this
            ->changeTracker
            ->method('wasModifiedSince')
            ->willReturnCallback(function (string $_, string $directory) {
                return realpath($directory) === realpath(__DIR__ . '/fixtures/c');
            });

        $this->changeTracker->method('getCurrentStateId')->willReturn('aaa');

        $this->state->setTargetStateId($targetIdB, 'aaa', []);
        $this->state->setTargetStateId($targetIdA, 'aaa', []);

        $this->setupDefinitions([$targetB, $targetC, $targetA]);

        $this->buildExecutor->executeTarget($targetIdA, ContextFactory::create());
    }

    public function testWillNotRebuildIfNoDependenciesWereRebuiltDeeplyNested(): void
    {
        $actionC = $this->createMock(BuildAction::class);
        $actionC->expects(self::never())->method('execute')->willReturn(BuildResult::ok([]));
        $targetIdC = new TargetId(__DIR__ . '/fixtures/c', 'c');
        $targetC = new Target($targetIdC, new NoOp());

        $actionB = $this->createMock(BuildAction::class);
        $actionB->expects(self::never())->method('execute')->willReturn(BuildResult::ok([]));
        $targetIdB = new TargetId(__DIR__ . '/fixtures/b', 'b');
        $targetB = new Target($targetIdB, new NoOp(), [$targetIdC]);

        $actionA = $this->createMock(BuildAction::class);
        $actionA->expects(self::never())->method('execute')->willReturn(BuildResult::ok([]));
        $targetIdA = new TargetId(__DIR__ . '/fixtures/a', 'a');
        $targetA = new Target($targetIdA, $actionA, [$targetIdB]);

        $this
            ->changeTracker
            ->method('wasModifiedSince')
            ->willReturnCallback(function (string $_, string $__) {
                return false;
            });

        $this->changeTracker->method('getCurrentStateId')->willReturn('aaa');

        $this->state->setTargetStateId($targetIdC, 'aaa', [new ContainerImage('a', 'a', 'a')]);
        $this->state->setTargetStateId($targetIdB, 'aaa', [new ContainerImage('b', 'b', 'b')]);
        $this->state->setTargetStateId($targetIdA, 'aaa', [new ContainerImage('c', 'c', 'c')]);

        $this->setupDefinitions([$targetB, $targetC, $targetA]);

        $result = $this->buildExecutor->executeTarget($targetIdA, ContextFactory::create());

        self::assertEquals(
            BuildResult::ok([
                new ContainerImage('a', 'a', 'a'),
                new ContainerImage('b', 'b', 'b'),
                new ContainerImage('c', 'c', 'c'),
            ]),
            $result
        );
    }

    public function testWillSetStateForSuccessfulTargets(): void
    {
        $this->initDefaultTargetOutput();

        $this->changeTracker->method('getCurrentStateId')->willReturn('aaa');

        $targetIdA = new TargetId(__DIR__ . '/fixtures/a', 'a');
        $targetIdB = new TargetId(__DIR__ . '/fixtures/b', 'b');

        $this->setupDefinitions([
            new Target($targetIdA, new NoOp()),
            new Target($targetIdB, new NoOp(), [$targetIdA]),
        ]);

        $this->buildExecutor->executeTarget($targetIdB, ContextFactory::create());

        self::assertEquals(['aaa', []], $this->state->getStateIdForTarget($targetIdA));
        self::assertEquals(['aaa', []], $this->state->getStateIdForTarget($targetIdB));
    }

    /**
     * @param non-empty-list<Target> $map
     */
    private function setupDefinitions(array $map): void
    {
        $this
            ->buildDefinitionsLoader
            ->method('target')
            ->willReturnCallback(function (TargetId $targetId) use ($map) {
                foreach ($map as $mapTarget) {
                    if ($mapTarget->id()->toString() === $targetId->toString()) {
                        return $mapTarget;
                    }
                }

                throw new RuntimeException(sprintf('Unexpected target id "%s"', $targetId->toString()));
            });
    }

    private function initDefaultTargetOutput(): void
    {
        $targetOutput = $this->createMock(TargetOutput::class);
        $targetOutput->method('finalize')->willReturn(new CollectedTargetOutput('', ''));
        $this->buildOutput->method('startTarget')->willReturn($targetOutput);
    }
}
