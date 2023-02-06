<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Actions\Kubernetes;

use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\InvalidInputFile;

final class InvalidInputFileTest extends TestCase
{
    public function testInvalidInputFile(): void
    {
        $exception = InvalidInputFile::notAnArray('foo');

        $this->assertSame('The file at foo is not an array', $exception->getMessage());
    }
}
