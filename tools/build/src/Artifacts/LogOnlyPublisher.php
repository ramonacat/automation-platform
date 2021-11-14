<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use function count;
use const PHP_EOL;

/**
 * @implements Publisher<Artifact>
 */
final class LogOnlyPublisher implements Publisher
{
    /**
     * @var list<Artifact>
     */
    private array $artifacts = [];

    public function __construct(private Ansi $ansi)
    {
    }

    public function publishes(): string
    {
        return Artifact::class;
    }

    public function publish(Artifact $artifact): void
    {
        $this->artifacts[] = $artifact;
    }

    public function print(): void
    {
        if (count($this->artifacts) === 0) {
            return;
        }

        $this
            ->ansi
            ->color([SGR::COLOR_FG_YELLOW])
            ->text('Generated artifacts: ' . PHP_EOL)
            ->nostyle();

        foreach ($this->artifacts as $artifact) {
            $this
                ->ansi
                ->text('    * ')
                ->text($artifact->name())
                ->text(PHP_EOL);
        }
    }
}
