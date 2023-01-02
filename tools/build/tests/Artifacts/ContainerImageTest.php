<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild\Artifacts;

use function assert;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\Artifacts\DisplayName;
use ReflectionClass;

final class ContainerImageTest extends TestCase
{
    public function testHasKey(): void
    {
        $artifact = new ContainerImage('key', 'name', 'tag');

        self::assertSame('key', $artifact->key());
    }

    public function testHasName(): void
    {
        $artifact = new ContainerImage('key', 'name', 'tag');

        self::assertSame('name:tag', $artifact->name());
    }

    public function testHasDisplayName(): void
    {
        $reflectionClass = new ReflectionClass(ContainerImage::class);

        $displayName = null;

        foreach ($reflectionClass->getAttributes() as $attribute) {
            if ($attribute->getName() === DisplayName::class) {
                $displayNameAttribute = $attribute->newInstance();
                assert($displayNameAttribute instanceof DisplayName);
                $displayName = $displayNameAttribute->name();
            }
        }
        self::assertSame('Container Image', $displayName);
    }
}
