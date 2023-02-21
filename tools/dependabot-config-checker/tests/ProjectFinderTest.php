<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformToolDependabotConfigChecker;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformToolDependabotConfigChecker\ProjectFinder;

final class ProjectFinderTest extends TestCase
{
    public function testCanFindProjects(): void
    {
        $projects = ProjectFinder::find(__DIR__ . '/fixtures/projects');

        self::assertSame([
            'services/service-a/',
            'agents/agent-a/',
            'libraries/csharp/library-a/',
            'libraries/rust/library-b/',
        ], $projects);
    }
}
