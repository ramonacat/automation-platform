<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\State;

interface StateStorage
{
    public function get(): State;
    public function set(State $state): void;
}
