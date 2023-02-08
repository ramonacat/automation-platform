<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformToolDependabotConfigChecker;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformToolDependabotConfigChecker\DockerfileFinder;

final class DockerfileFinderTest extends TestCase
{
    public function testWillFindDockerfiles(): void
    {
        $finder = new DockerfileFinder(__DIR__ . '/fixtures/a/');
        $results = $finder->find();

        self::assertSame(
            [
                __DIR__ . '/fixtures/a/',
            ],
            $results
        );
    }
}
