<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once __DIR__.'/../vendor/autoload.php';

$connection = new AMQPStreamConnection(
    'rmq-events',
    '5672',
    file_get_contents('/etc/svc-events/secrets/rmq-events-default-user/username'),
    file_get_contents('/etc/svc-events/secrets/rmq-events-default-user/password')
);
$channel = $connection->channel();
$channel->exchange_declare('events', 'fanout', false, true, false, false);

fprintf(STDERR, 'Starting producing...'.PHP_EOL);
while (true) {
    $channel->basic_publish(new AMQPMessage('testingtesting'.time()), 'events');
    sleep(1);
    fprintf(STDERR, 'Message produced...'.PHP_EOL);
    fflush(STDERR);
}
