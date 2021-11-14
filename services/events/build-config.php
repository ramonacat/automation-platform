<?php

use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Artifacts\ContainerImage;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

$rustTargetGenerator = new TargetGenerator(__DIR__);

$tag = str_replace('.', '', uniqid('', true));

$imageName = 'automation-platform-svc-events';
$migrationImageName = 'ap-svc-events-migrations';

$override = static fn(Context $context):string => <<<EOT
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
                  image: {$context->artifactCollector()->getByKey(__DIR__, 'image-migrations')->name()}
              containers:
                - name: app
                  image: {$context->artifactCollector()->getByKey(__DIR__, 'image-service')->name()}
        EOT;

return new BuildDefinition(
    array_merge(
        [
            new Target(
                'build-dev',
                new PutFile(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override),
                array_merge(
                    $rustTargetGenerator->targetIds(),
                    [new TargetId(__DIR__, 'build-images-dev')]
                )
            ),
            new Target(
                'build-images-dev',
                new Group(
                    [
                        new RunProcess('docker build -t ' . $imageName . ':' . $tag . ' -f docker/Dockerfile ../../', [new ContainerImage('image-service', $imageName, $tag)]),
                        new RunProcess('docker build -t ' . $migrationImageName . ':' . $tag . ' -f docker/migrations.Dockerfile .', [new ContainerImage('image-migrations', $migrationImageName, $tag)]),
                    ]
                )
            ),
            new Target('deploy-dev', new RunProcess('kubectl --context minikube apply -k k8s/overlays/dev'), [new TargetId(__DIR__, 'build-dev')]),
        ],
        $rustTargetGenerator->targets()
    )
);