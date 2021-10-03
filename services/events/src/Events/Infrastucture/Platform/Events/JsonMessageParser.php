<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events;

use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PhpAmqpLib\Message\AMQPMessage;
use function Safe\json_decode;

final class JsonMessageParser implements MessageParser
{
    public function parse(AMQPMessage $message): array
    {
        $decodedJson = json_decode($message->body, true);

        $validator = new Validator(); // todo inject
        $validator->resolver()->registerFile('https://schemas.ramona.fun/automation-platform/v1/events/events.schema.json', '/etc/svc-events/schemas/events.schema.json'); // todo the path should be configurable
        $result = $validator->validate(Helper::toJSON($decodedJson), 'https://schemas.ramona.fun/automation-platform/v1/events/events.schema.json');

        if ($result->hasError()) {
            throw InvalidMessage::forRawString($message->body, $result->error()->message());
        }

        return $decodedJson;
    }
}
