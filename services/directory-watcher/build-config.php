<?php

use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Actions\RunProcess;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;
use Ramona\AutomationPlatformLibBuild\Configuration\Configuration;

$tag = str_replace('.', '', uniqid('', true));

$imageWithTag =  'automation-platform-svc-directory-watcher:' . $tag;
$migrationsImageWithTag =  'automation-platform-svc-migrations:' . $tag;
$override = function (Configuration $configuration) use($imageWithTag, $migrationsImageWithTag) {
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

return new BuildDefinition([
    new Target('build-dev', new Group([
        new PutRuntimeConfiguration(__DIR__.'/runtime.configuration.json'),
        new RunProcess('docker build -t ' . $imageWithTag . ' -f docker/Dockerfile ../../'),
        new RunProcess('docker build -t ' . $migrationsImageWithTag . ' -f docker/migrations.Dockerfile .'),
        new PutFile(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override),
    ]), [
        new TargetId(__DIR__, 'clippy'),
        new TargetId(__DIR__, 'fmt'),
        new TargetId(__DIR__, 'tests-unit'),
    ]),
    new Target('clippy', new RunProcess('cargo clippy')),
    new Target('tests-unit', new RunProcess('cargo test')),
    new Target('deploy-dev', new RunProcess('kubectl --context minikube apply -k k8s/overlays/dev'), [new TargetId(__DIR__, 'build-dev'), new TargetId(__DIR__.'/../events/', 'deploy-dev')]),
    new Target('fmt', new RunProcess('cargo fmt -- --check')),
]);