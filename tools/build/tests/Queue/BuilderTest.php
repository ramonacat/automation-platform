<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Queue;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\Queue\Builder;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\Targets\TargetQueue;
use RuntimeException;
use function sprintf;

final class BuilderTest extends TestCase
{
    /**
     * @var BuildDefinitionsLoader&MockObject
     */
    private BuildDefinitionsLoader $buildDefinitionsLoader;

    private Builder $queueBuilder;

    public function setUp(): void
    {
        $this->buildDefinitionsLoader = $this->createMock(BuildDefinitionsLoader::class);
        $this->queueBuilder = new Builder($this->buildDefinitionsLoader);
    }

    public function testWillReturnJustSelfForNoDependencies(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId('.', 'build'), new NoOp()),
        ]);

        $result = $this->queueBuilder->build(new TargetId('.', 'build'));

        self::assertObjectEquals(TargetQueue::fromArray([new TargetId('.', 'build')]), $result);
    }

    public function testWillPutDependencyBeforeSelf(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId('.', 'build-dep'), new NoOp()),
            new Target(new TargetId('.', 'build'), new NoOp(), [new TargetId('.', 'build-dep')]),
        ]);

        $result = $this->queueBuilder->build(new TargetId('.', 'build'));

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

        $result = $this->queueBuilder->build(new TargetId('.', 'build'));

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

        $result = $this->queueBuilder->build(new TargetId('.', 'build'));

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
            new Target(new TargetId(__DIR__ . '/../fixtures/b/', 'build-dev'), new NoOp(), [new TargetId(__DIR__ . '/../fixtures/b', 'check')]),
            new Target(new TargetId(__DIR__ . '/../fixtures/b/', 'check'), new NoOp()),
            new Target(new TargetId(__DIR__ . '/../fixtures/b/', 'deploy-dev'), new NoOp(), [new TargetId(__DIR__ . '/../fixtures/b', 'build-dev')]),

            new Target(new TargetId(__DIR__ . '/../fixtures/a/', 'build-dev'), new NoOp(), [new TargetId(__DIR__ . '/../fixtures/a', 'check')]),
            new Target(new TargetId(__DIR__ . '/../fixtures/a/', 'check'), new NoOp()),
            new Target(new TargetId(__DIR__ . '/../fixtures/a/', 'deploy-dev'), new NoOp(), [new TargetId(__DIR__ . '/../fixtures/b', 'deploy-dev')]),
        ]);

        $result = $this->queueBuilder->build(new TargetId(__DIR__ . '/../fixtures/a', 'deploy-dev'));

        self::assertObjectEquals(TargetQueue::fromArray([
            new TargetId(__DIR__ . '/../fixtures/b', 'check'),
            new TargetId(__DIR__ . '/../fixtures/b', 'build-dev'),
            new TargetId(__DIR__ . '/../fixtures/b', 'deploy-dev'),
            new TargetId(__DIR__ . '/../fixtures/a', 'deploy-dev'),
        ]), $result);
    }

    public function testConsidersTargetsWithDifferentPathsToTheSameDirectoryTheSame(): void
    {
        $this->setupDefinitions([
            new Target(new TargetId(__DIR__ . '/../fixtures/a', 'check'), new NoOp(), [new TargetId(__DIR__ . '/../fixtures/b', 'check'), new TargetId(__DIR__ . '/../fixtures/a/../c', 'check')]),
            new Target(new TargetId(__DIR__ . '/../fixtures/b', 'check'), new NoOp(), [new TargetId(__DIR__ . '/../fixtures/c', 'check')]),
            new Target(new TargetId(__DIR__ . '/../fixtures/c', 'check'), new NoOp()),
        ]);

        $result = $this->queueBuilder->build(new TargetId(__DIR__ . '/../fixtures/a', 'check'));

        self::assertObjectEquals(TargetQueue::fromArray([
            new TargetId(__DIR__ . '/../fixtures/c', 'check'),
            new TargetId(__DIR__ . '/../fixtures/b', 'check'),
            new TargetId(__DIR__ . '/../fixtures/a', 'check'),
        ]), $result);
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
