<?php

declare(strict_types=1);

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Ramona\AutomationPlatformSvcEvents\Platform\Kubernetes\SecretProvider;

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once __DIR__ . '/../vendor/autoload.php';

$secretProvider = new SecretProvider('/etc/svc-events/secrets/');
$secret = $secretProvider->read('rmq-events-default-user');

$connection = new AMQPStreamConnection(
    'rmq-events',
    '5672',
    $secret->username(),
    $secret->password()
);
$channel = $connection->channel();
$channel->exchange_declare('events', 'fanout', false, true, false, false);

fprintf(STDERR, 'Starting producing...' . PHP_EOL);
while (true) {
    $channel->basic_publish(new AMQPMessage('testingtesting' . (string)time()), 'events');
    sleep(1);
    fprintf(STDERR, 'Message produced...' . PHP_EOL);
    fflush(STDERR);
}
