<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events;

use PhpAmqpLib\Message\AMQPMessage;

interface MessageParser
{
    public function parse(AMQPMessage $message): array;
}
