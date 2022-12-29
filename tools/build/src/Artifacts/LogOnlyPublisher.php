<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use function count;
use function get_class;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\Context;
use ReflectionClass;

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

    public function print(Ansi $ansi, Context $context): void
    {
        if (count($this->artifacts) === 0) {
            return;
        }

        $ansi
            ->color([SGR::COLOR_FG_YELLOW])
            ->text('Generated artifacts: ' . PHP_EOL)
            ->nostyle();

        foreach ($this->artifacts as $artifact) {
            $artifactName = get_class($artifact);
            $artifactReflection = new ReflectionClass($artifact);
            foreach ($artifactReflection->getAttributes() as $attribute) {
                if ($attribute->getName() === DisplayName::class) {
                    $attributeInstance = $attribute->newInstance();
                    /** @var DisplayName $attributeInstance */
                    $artifactName = $attributeInstance->name();
                }
            }

            $ansi
                ->text('    * ')
                ->color([SGR::COLOR_FG_PURPLE])
                ->text($artifactName)
                ->nostyle()
                ->text(' ')
                ->text($artifact->name())
                ->text(PHP_EOL);
        }
    }
}
