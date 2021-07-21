<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Platform;

use RuntimeException;
use function sprintf;

final class SecretNotFoundException extends RuntimeException
{
    /**
     * @psalm-pure
     */
    public static function forSecretName(
        string $secretName
    ): self {
        return new self(sprintf('Secret "%s" does not exist', $secretName));
    }
}
