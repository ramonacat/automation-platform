<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Platform;

interface SecretProviderInterface
{
    /**
     * @psalm-taint-source system_secret
     */
    public function read(string $name): Secret;
}
