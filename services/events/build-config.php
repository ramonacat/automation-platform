<?php

declare(strict_types=1);

use Ramona\AutomationPlatformLibBuild\ActionGroup;
use Ramona\AutomationPlatformLibBuild\CopyFile;
use Ramona\AutomationPlatformLibBuild\PutFile;
use Ramona\AutomationPlatformLibBuild\RunProcess;

$tag = str_replace('.', '', uniqid('', true));

$imageWithTag = 'automation-platform-svc-events:' . $tag;
$override = <<<EOT
    apiVersion: apps/v1
    kind: Deployment
    metadata:
      name: svc-events
    spec:
      template:
        metadata:
          labels:
            app: svc-events
        spec:
          containers:
            - name: svc-events
              image: {$imageWithTag}
    EOT;

return [
    'build-dev' => new ActionGroup([
        new CopyFile(__DIR__ . '/../../schemas/events.schema.json', __DIR__ . '/events.schema.json'), // todo support workspace-relative paths
        new RunProcess('docker build -t ' . $imageWithTag . ' -f docker/Dockerfile .'),
        new PutFile(__DIR__ . '/k8s/overlays/dev/deployment.yaml', $override)
    ]),
    'check' => new ActionGroup([
        new RunProcess('php vendor/bin/ecs'),
        new RunProcess('php vendor/bin/psalm')
    ]),
    'cs-fix' => new RunProcess('php vendor/bin/ecs --fix')
];
