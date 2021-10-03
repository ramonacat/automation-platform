<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events;

use RuntimeException;

final class InvalidMessage extends RuntimeException
{
    private function __construct(private string $rawMessage, string $description)
    {
        parent::__construct("Invalid message: $description");
    }

    public static function forRawString(string $rawMessage, string $description): self
    {
        return new self($rawMessage, $description);
    }

    public function rawMessage(): string
    {
        return $this->rawMessage;
    }
}
