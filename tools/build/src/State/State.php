<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\State;

use Ramona\AutomationPlatformLibBuild\Artifacts\Artifact;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

final class State
{
    /**
     * @var array<string, array{0:string,1:list<Artifact>}>
     */
    private array $targetsToStateIds = [];

    /**
     * @param list<Artifact> $artifacts
     */
    public function setTargetStateId(TargetId $targetId, string $currentStateId, array $artifacts): void
    {
        $this->targetsToStateIds[$targetId->toString()] = [$currentStateId, $artifacts];
    }

    /**
     * @return array{0:string,1:list<Artifact>}
     */
    public function getStateIdForTarget(TargetId $targetId): ?array
    {
        return $this->targetsToStateIds[$targetId->toString()] ?? null;
    }
}
