<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\CodeCoverage;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\CodeCoverage\InvalidCoverageFile;

final class InvalidCoverageFileTest extends TestCase
{
    public function testNotAnArray(): void
    {
        $exception = InvalidCoverageFile::notAnArray('foo', 'bar');
        
        self::assertSame('Key "bar" in file "foo" is not an array', $exception->getMessage());
    }
}
