<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Artifacts;

use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class Collector
{
    /**
     * @var array<string, array<string, Artifact>>
     */
    private array $artifacts = [];

    public function collect(TargetId $fromTarget, Artifact $artifact): void
    {
        if (isset($this->artifacts[$fromTarget->path()][$artifact->key()])) {
            throw new ArtifactKeyAlreadyUsed($fromTarget->path(), $artifact->key());
        }

        $this->artifacts[$fromTarget->path()][$artifact->key()] = $artifact;
    }

    public function getByKey(string $directory, string $key): Artifact
    {
        return $this->artifacts[$directory][$key] ?? throw new ArtifactNotFound($directory, $key);
    }

    /**
     * @return list<Artifact>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->artifacts as $artifacts) {
            foreach ($artifacts as $artifact) {
                $result[] = $artifact;
            }
        }

        return $result;
    }
}
