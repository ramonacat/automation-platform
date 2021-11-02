<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\BuildDefinitionsLoader;
use Ramona\AutomationPlatformLibBuild\BuildExecutor;
use Ramona\AutomationPlatformLibBuild\CyclicDependencyFound;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;
use Ramona\AutomationPlatformLibBuild\TargetQueue;
use RuntimeException;
use function sprintf;

final class BuildExecutorTest extends TestCase
{
    /**
     * @var BuildDefinitionsLoader&MockObject
     */
    private BuildDefinitionsLoader $buildDefinitionsLoader;
    private BuildExecutor $buildExecutor;

    public function setUp(): void
    {
        $this->buildDefinitionsLoader = $this->createMock(BuildDefinitionsLoader::class);
        $this->buildExecutor = new BuildExecutor($this->buildDefinitionsLoader);
    }

    public function testWillReturnJustSelfForNoDependencies(): void
    {
        $this->setupDefinitions([
            [new TargetId('.', 'build'), new Target('build', new NoOp())],
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId('.', 'build'));

        self::assertObjectEquals(TargetQueue::fromArray([new TargetId('.', 'build')]), $result);
    }

    public function testWillPutDependencyBeforeSelf(): void
    {
        $this->setupDefinitions([
            [new TargetId('.', 'build-dep'), new Target('build-dep', new NoOp())],
            [new TargetId('.', 'build'), new Target('build', new NoOp(), [new TargetId('.', 'build-dep')])],
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
            [new TargetId('.', 'build-dep-1'), new Target('build-dep-1', new NoOp())],
            [new TargetId('.', 'build-dep'), new Target('build-dep-1', new NoOp(), [new TargetId('.', 'build-dep-1')])],
            [new TargetId('.', 'build'), new Target('build-dep-1', new NoOp(), [new TargetId('.', 'build-dep')])],
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
            [new TargetId('.', 'build-dep-2'), new Target('build-dep-2', new NoOp(), [new TargetId('.', 'build-dep-1')])],
            [new TargetId('.', 'build-dep-1'), new Target('build-dep-1', new NoOp())],
            [new TargetId('.', 'build-dep'), new Target('build-dep', new NoOp(), [new TargetId('.', 'build-dep-1')])],
            [new TargetId('.', 'build'), new Target('build', new NoOp(), [new TargetId('.', 'build-dep'), new TargetId('.', 'build-dep-2')])],
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

        $this->setupDefinitions([
            [new TargetId('.', 'build-dep-1'), new Target('build-dep-1', new NoOp(), [new TargetId('.', 'build')])],
            [new TargetId('.', 'build-dep'), new Target('build-dep', new NoOp(), [new TargetId('.', 'build-dep-1')])],
            [new TargetId('.', 'build'), new Target('build', new NoOp(), [new TargetId('.', 'build-dep')])],
        ]);

        $this->expectException(CyclicDependencyFound::class);
        $this->buildExecutor->buildQueue(new TargetId('.', 'build'));
    }

    public function testWorksOnRepeatingDependencies(): void
    {
        $this->setupDefinitions([
            [new TargetId('b', 'build-dev'), new Target('build-dev', new NoOp(), [new TargetId('b', 'check')])],
            [new TargetId('b', 'check'), new Target('check', new NoOp())],
            [new TargetId('b', 'deploy-dev'), new Target('deploy-dev', new NoOp(), [new TargetId('b', 'build-dev')])],

            [new TargetId('a', 'build-dev'), new Target('build-dev', new NoOp(), [new TargetId('a', 'check')])],
            [new TargetId('a', 'check'), new Target('check', new NoOp())],
            [new TargetId('a', 'deploy-dev'), new Target('deploy-dev', new NoOp(), [new TargetId('b', 'deploy-dev')])],
        ]);

        $result = $this->buildExecutor->buildQueue(new TargetId('a', 'deploy-dev'));

        self::assertObjectEquals(TargetQueue::fromArray([
            new TargetId('b', 'check'),
            new TargetId('b', 'build-dev'),
            new TargetId('b', 'deploy-dev'),
            new TargetId('a', 'deploy-dev'),
            ]), $result);
    }

    /**
     * @param non-empty-list<array{0:TargetId,1:Target}> $map
     */
    private function setupDefinitions(array $map): void
    {
        $this
            ->buildDefinitionsLoader
            ->method('target')
            ->willReturnCallback(function (TargetId $targetId) use ($map) {
                foreach ($map as $mapTarget) {
                    if ($mapTarget[0]->toString() === $targetId->toString()) {
                        return $mapTarget[1];
                    }
                }

                throw new RuntimeException(sprintf('Unexpected target id "%s"', $targetId->toString()));
            });
    }
}
