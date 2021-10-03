<?php

declare(strict_types=1);

use Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events\AMQPEndpoint;
use Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events\EventSubscriber;
use Ramona\AutomationPlatformSvcEvents\Events\Infrastucture\Platform\Events\JsonMessageParser;
use Ramona\AutomationPlatformSvcEvents\Platform\Kubernetes\SecretProvider;
use function Safe\json_encode;

error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once __DIR__ . '/../vendor/autoload.php';

$secretProvider = new SecretProvider('/etc/svc-events/secrets/');
$secret = $secretProvider->read('rmq-events-default-user');

$subscriber = new EventSubscriber(new AMQPEndpoint('rmq-events', 5672, $secret), new JsonMessageParser());

$subscriber->subscribe('events-svc', function (array $event) {
    fprintf(STDERR, "event: %s\n", json_encode($event));
});
fputs(STDERR, 'Starting consuming...' . PHP_EOL);
$subscriber->waitForMessages();
fputs(STDERR, 'Exiting...' . PHP_EOL);
