<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events;

use Ramona\AutomationPlatformSvcEvents\Platform\Secret;

/**
 * @psalm-immutable
 */
final class AMQPEndpoint
{
    public function __construct(private string $hostname, private int $port, private Secret $secret)
    {
    }

    public function hostname(): string
    {
        return $this->hostname;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function username(): string
    {
        return $this->secret->username();
    }

    public function password(): string
    {
        return $this->secret->password();
    }
}
