<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Platform;

use Exception;
use RuntimeException;
use function sprintf;

final class SecretNotFoundException extends RuntimeException
{
    /**
     * @psalm-pure
     */
    public static function forSecretName(
        string $secretName,
        ?Exception $previous = null
    ): self {
        return new self(sprintf('Secret "%s" does not exist', $secretName), 0, $previous);
    }
}
