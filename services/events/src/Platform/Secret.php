<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Platform;

/**
 * @psalm-immutable
 */
final class Secret
{
    public function __construct(
        private string $username,
        private string $password
    ) {
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }
}
