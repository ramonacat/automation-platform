<?php

use Ramona\AutomationPlatformLibBuild\Actions\ActionGroup;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

$tag = str_replace('.', '', uniqid('', true));

$imageWithTag =  'automation-platform-svc-directory-watcher:' . $tag;
$migrationsImageWithTag =  'automation-platform-svc-migrations:' . $tag;
$override = <<<EOT
        apiVersion: apps/v1
        kind: Deployment
        metadata:
          name: svc-directory-watcher
        spec:
          template:
            metadata:
              labels:
                app: svc-directory-watcher
            spec:
              initContainers:
                - name: migrations
                  image: {$migrationsImageWithTag}
              containers:
                - name: app
                  image: {$imageWithTag}
        EOT;

return new BuildDefinition([
    new Target('build-dev', new ActionGroup([
        new RunProcess('docker build -t ' . $imageWithTag . ' -f docker/Dockerfile ../../'),
        new RunProcess('docker build -t ' . $migrationsImageWithTag . ' -f docker/migrations.Dockerfile .'),
        new PutFile(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override)
    ]), [new TargetId(__DIR__, 'check'), new TargetId(__DIR__.'/../events/', 'build-dev')]), // todo there should be no dependency on build-dev, but on deploy-dev instead, when it exists
    new Target('check', new RunProcess('cargo clippy'))
]);