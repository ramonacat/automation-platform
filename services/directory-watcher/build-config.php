<?php

use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

$rustTargetGenerator = new TargetGenerator(__DIR__);

$tag = str_replace('.', '', uniqid('', true));

$imageWithTag =  'automation-platform-svc-directory-watcher:' . $tag;
$migrationsImageWithTag =  'automation-platform-svc-migrations:' . $tag;
$override = static function () use($imageWithTag, $migrationsImageWithTag):string {
    return <<<EOT
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
    };

return new BuildDefinition(
    array_merge(
        [
            new Target(
                'build-dev',
                new Group(
                    [
                        new PutRuntimeConfiguration(__DIR__.'/runtime.configuration.json'),
                        new RunProcess('docker build -t ' . $imageWithTag . ' -f docker/Dockerfile ../../'),
                        new RunProcess('docker build -t ' . $migrationsImageWithTag . ' -f docker/migrations.Dockerfile .'),
                        new PutFile(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override),
                    ]
                ),
                $rustTargetGenerator->targetIds()
            ),
            new Target(
                'deploy-dev',
                new RunProcess('kubectl --context minikube apply -k k8s/overlays/dev'),
                [new TargetId(__DIR__, 'build-dev'), new TargetId(__DIR__.'/../events/', 'deploy-dev')]
            ),
        ],
        $rustTargetGenerator->targets()
    )
);