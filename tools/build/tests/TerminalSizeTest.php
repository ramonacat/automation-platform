<?php

declare(strict_types=1);

namespace Tests\Ramona\AutomationPlatformLibBuild;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramona\AutomationPlatformLibBuild\TerminalSize;

final class TerminalSizeTest extends TestCase
{
    /**
     * @dataProvider dataInvalidDimension
     */
    public function testDoesNotAllowInvalidWidth(int $width): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TerminalSize($width, 100);
    }

    /**
     * @dataProvider dataInvalidDimension
     */
    public function testDoesNotAllowInvalidHeight(int $height): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TerminalSize(100, $height);
    }

    /**
     * @return iterable<int, array{0:int}>
     */
    public function dataInvalidDimension(): iterable
    {
        yield [0];
        yield [-1];
        yield [-200];
    }
}
