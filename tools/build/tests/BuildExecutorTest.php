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
use Ramona\AutomationPlatformLibBuild\BuildOutput\BuildOutput;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;
use Ramona\AutomationPlatformLibBuild\CyclicDependencyFound;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Definition\BuildExecutor;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\Targets\TargetQueue;
use RuntimeException;
use function sprintf;

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

    public function setUp(): void
    {
        $this->buildDefinitionsLoader = $this->createMock(BuildDefinitionsLoader::class);
        $this->buildOutput = $this->createMock(BuildOutput::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->buildExecutor = new BuildExecutor(
            $this->logger,
            $this->buildOutput,
            $this->buildDefinitionsLoader,
            Configuration::fromJsonString('{}'),
            new BuildFacts('test', false, 1, 1)
        );
    }

    public function testWillReturnJustSelfForNoDependencies(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId('.', 'build'), new NoOp()),
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId('.', 'build'));

        self::assertObjectEquals(TargetQueue::fromArray([new TargetId('.', 'build')]), $result);
    }

    public function testWillPutDependencyBeforeSelf(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId('.', 'build-dep'), new NoOp()),
            new Target(new TargetId('.', 'build'), new NoOp(), [new TargetId('.', 'build-dep')]),
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId('.', 'build'));

        self::assertObjectEquals(
            TargetQueue::fromArray(
                [
                new TargetId('.', 'build-dep'),
                new TargetId('.', 'build'),
            ]
            ),
            $result
        );
    }

    public function testCanHandleMultipleLevelsOfDependencies(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId('.', 'build-dep-1'), new NoOp()),
            new Target(new TargetId('.', 'build-dep'), new NoOp(), [new TargetId('.', 'build-dep-1')]),
            new Target(new TargetId('.', 'build'), new NoOp(), [new TargetId('.', 'build-dep')]),
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId('.', 'build'));

        self::assertObjectEquals(TargetQueue::fromArray([
            new TargetId('.', 'build-dep-1'),
            new TargetId('.', 'build-dep'),
            new TargetId('.', 'build'),
        ]), $result);
    }

    public function testCanHandleRepeatingDependencies(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId('.', 'build-dep-2'), new NoOp(), [new TargetId('.', 'build-dep-1')]),
            new Target(new TargetId('.', 'build-dep-1'), new NoOp()),
            new Target(new TargetId('.', 'build-dep'), new NoOp(), [new TargetId('.', 'build-dep-1')]),
            new Target(new TargetId('.', 'build'), new NoOp(), [new TargetId('.', 'build-dep'), new TargetId('.', 'build-dep-2')]),
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId('.', 'build'));

        self::assertObjectEquals(TargetQueue::fromArray([
            new TargetId('.', 'build-dep-1'),
            new TargetId('.', 'build-dep'),
            new TargetId('.', 'build-dep-2'),
            new TargetId('.', 'build'),
        ]), $result);
    }

    public function testThrowsOnCycles(): void
    {
        $this->markTestSkipped('Need to figure out how to do the cyclic check...');
        /*
                $this->setupDefinitions([
                    [new TargetId('.', 'build-dep-1'), new Target('build-dep-1', new NoOp(), [new TargetId('.', 'build')])],
                    [new TargetId('.', 'build-dep'), new Target('build-dep', new NoOp(), [new TargetId('.', 'build-dep-1')])],
                    [new TargetId('.', 'build'), new Target('build', new NoOp(), [new TargetId('.', 'build-dep')])],
                ]);

                $this->expectException(CyclicDependencyFound::class);
                $this->buildExecutor->buildQueue(new TargetId('.', 'build'));*/
    }

    public function testWorksOnRepeatingDependencies(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId(__DIR__ . '/b/', 'build-dev'), new NoOp(), [new TargetId(__DIR__ . '/b', 'check')]),
            new Target(new TargetId(__DIR__ . '/b/', 'check'), new NoOp()),
            new Target(new TargetId(__DIR__ . '/b/', 'deploy-dev'), new NoOp(), [new TargetId(__DIR__ . '/b', 'build-dev')]),

            new Target(new TargetId(__DIR__ . '/a/', 'build-dev'), new NoOp(), [new TargetId(__DIR__ . '/a', 'check')]),
            new Target(new TargetId(__DIR__ . '/a/', 'check'), new NoOp()),
            new Target(new TargetId(__DIR__ . '/a/', 'deploy-dev'), new NoOp(), [new TargetId(__DIR__ . '/b', 'deploy-dev')]),
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId(__DIR__ . '/a', 'deploy-dev'));

        self::assertObjectEquals(TargetQueue::fromArray([
            new TargetId(__DIR__ . '/b', 'check'),
            new TargetId(__DIR__ . '/b', 'build-dev'),
            new TargetId(__DIR__ . '/b', 'deploy-dev'),
            new TargetId(__DIR__ . '/a', 'deploy-dev'),
        ]), $result);
    }

    public function testConsidersTargetsWithDifferentPathsToTheSameDirectoryTheSame(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId(__DIR__ . '/a', 'check'), new NoOp(), [new TargetId(__DIR__ . '/b', 'check'), new TargetId(__DIR__ . '/a/../c', 'check')]),
            new Target(new TargetId(__DIR__ . '/b', 'check'), new NoOp(), [new TargetId(__DIR__ . '/c', 'check')]),
            new Target(new TargetId(__DIR__ . '/c', 'check'), new NoOp()),
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId(__DIR__ . '/a', 'check'));

        self::assertObjectEquals(TargetQueue::fromArray([
            new TargetId(__DIR__ . '/c', 'check'),
            new TargetId(__DIR__ . '/b', 'check'),
            new TargetId(__DIR__ . '/a', 'check'),
        ]), $result);
    }

    public function testCanExecuteATarget(): void
    {
        $targetId = new TargetId(__DIR__ . '/c', 'check');
        $action = $this->createMock(BuildAction::class);

        $action->expects(self::once())->method('execute')->willReturn(BuildResult::ok([]));

        $this->setupDefinitions([
            new Target($targetId, $action),
        ]);

        $this->buildExecutor->executeTarget($targetId);
    }

    public function testWillStartEachTarget(): void
    {
        $targetIdA = new TargetId(__DIR__ . '/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/c', 'b');

        $this->setupDefinitions([
            new Target($targetIdA, new NoOp()),
            new Target($targetIdB, new NoOp(), [$targetIdA]),
        ]);

        $this
            ->buildOutput
            ->expects(self::exactly(2))
            ->method('startTarget')
            ->withConsecutive([$targetIdA], [$targetIdB]);

        $this->buildExecutor->executeTarget($targetIdB);
    }

    public function testWillCollectAllArtifacts(): void
    {
        $artifactA = new ContainerImage('ramona-test-1', 'ramona/test1', 'latest');
        $artifactB = new ContainerImage('ramona-test-2', 'ramona/test2', 'latest');

        $targetIdA = new TargetId(__DIR__ . '/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/c', 'b');

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

        $result = $this->buildExecutor->executeTarget($targetIdB);

        self::assertEquals([$artifactA, $artifactB], $result->artifacts());
    }

    public function testWillFinalizeTheBuild(): void
    {
        $targetIdA = new TargetId(__DIR__ . '/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/c', 'b');

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
        $targetOutputB = $this->createMock(TargetOutput::class);

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
                $targetIdA->toString() => [$resultA, $targetOutputA],
                $targetIdB->toString() => [$resultB, $targetOutputB]
            ]);

        $this->buildExecutor->executeTarget($targetIdB);
    }

    public function testWillFailIfAnyTargetFailed(): void
    {
        $targetIdA = new TargetId(__DIR__ . '/c', 'a');
        $targetIdB = new TargetId(__DIR__ . '/c', 'b');

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

        $result = $this->buildExecutor->executeTarget($targetIdB);
        self::assertFalse($result->hasSucceeded());
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
}
