<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Platform\Kubernetes;

use Ramona\AutomationPlatformSvcEvents\Platform\Secret;
use Ramona\AutomationPlatformSvcEvents\Platform\SecretProviderInterface;
use function Safe\file_get_contents;

final class SecretProvider implements SecretProviderInterface
{
    public function __construct(
        private string $basePath
    ) {
    }

    public function read(string $name): Secret
    {
        $username = file_get_contents($this->basePath . '/' . $name . '/username');
        $password = file_get_contents($this->basePath . '/' . $name . '/password');

        return new Secret($username, $password);
    }
}
