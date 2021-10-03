<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class EventSubscriber
{
    private const EXCHANGE_NAME = 'events';
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;

    public function __construct(AMQPEndpoint $endpoint, private MessageParser $messageParser)
    {
        $this->connection = new AMQPStreamConnection($endpoint->hostname(), (string)$endpoint->port(), $endpoint->username(), $endpoint->password());
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare(self::EXCHANGE_NAME, 'fanout', false, true, false, false);
    }

    public function subscribe(string $subscriberQueueName, callable $onMessage): void
    {
        $this->channel->queue_declare($subscriberQueueName, durable: true, auto_delete: false);
        $this->channel->queue_bind($subscriberQueueName, self::EXCHANGE_NAME);

        $this->channel->basic_consume($subscriberQueueName, callback: function (AMQPMessage $message) use ($onMessage) {
            $onMessage($this->messageParser->parse($message));
            $message->ack();
        });
    }

    public function waitForMessages(): void
    {
        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
