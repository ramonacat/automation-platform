<?php

declare(strict_types=1);

use PhpAmqpLib\Message\AMQPMessage;
use Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\AMQPEndpoint;
use Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\EventSubscriber;
use Ramona\AutomationPlatformSvcEvents\Platform\Kubernetes\SecretProvider;

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once __DIR__ . '/../vendor/autoload.php';

$secretProvider = new SecretProvider('/etc/svc-events/secrets/');
$secret = $secretProvider->read('rmq-events-default-user');

$subscriber = new EventSubscriber(new AMQPEndpoint('rmq-events', 5672, $secret));

$subscriber->subscribe('events-svc', function (AMQPMessage $message) {
    fprintf(STDERR, 'Message received: %s' . PHP_EOL, $message->body);
});
fputs(STDERR, 'Starting consuming...' . PHP_EOL);
$subscriber->waitForMessages();
fputs(STDERR, 'Exiting...' . PHP_EOL);
