<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Platform;

interface SecretProviderInterface
{
    public function read(string $name): Secret;
}
