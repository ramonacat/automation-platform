<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Platform;

final class Secret
{
    public function __construct(
        private string $username,
        private string $password
    ) {
    }

    /**
     * @psalm-readonly
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * @psalm-readonly
     */
    public function password(): string
    {
        return $this->password;
    }
}
