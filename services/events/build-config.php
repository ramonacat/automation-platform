<?php

use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

$rustTargetGenerator = new TargetGenerator(__DIR__);

$tag = str_replace('.', '', uniqid('', true));

$imageWithTag =  'automation-platform-svc-events:' . $tag;
$migrationsImageWithTag =  'ap-svc-events-migrations:' . $tag;
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
              initContainers:
                - name: migrations
                  image: {$migrationsImageWithTag}
              containers:
                - name: app
                  image: {$imageWithTag}
        EOT;

return new BuildDefinition(
    array_merge(
        [
            new Target(
                'build-dev',
                new Group([
                    new RunProcess('docker build -t ' . $imageWithTag . ' -f docker/Dockerfile ../../'),
                    new RunProcess('docker build -t ' . $migrationsImageWithTag . ' -f docker/migrations.Dockerfile .'),
                    new PutFile(__DIR__.'/k8s/overlays/dev/deployment.yaml', fn() => $override)
                ]),
                $rustTargetGenerator->targetIds()
            ),
            new Target('deploy-dev', new RunProcess('kubectl --context minikube apply -k k8s/overlays/dev'), [new TargetId(__DIR__, 'build-dev')]),
        ],
        $rustTargetGenerator->targets()
    )
);