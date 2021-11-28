<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild;

use InvalidArgumentException;

final class TerminalSize
{
    /**
     * @var positive-int
     */
    private int $width;

    /**
     * @var positive-int
     */
    private int $height;

    public function __construct(int $width, int $height)
    {
        if ($width <= 0) {
            throw new InvalidArgumentException('Terminal width must be a positive integer');
        }

        if ($height <= 0) {
            throw new InvalidArgumentException('Terminal height must be a positive integer');
        }

        $this->width = $width;
        $this->height = $height;
    }

    /**
     * @return positive-int
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * @return positive-int
     */
    public function wrappingPoint(): int
    {
        return $this->width <= 4 ? 1 : $this->width - 4;
    }

    /**
     * @return positive-int
     */
    public function height(): int
    {
        return $this->height;
    }
}
