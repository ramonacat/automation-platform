<?php

$tag = str_replace('.', '', uniqid('', true));
$imageWithTag =  'automation-platform-svc-directory-watcher:' . $tag;
passthru('docker build -t ' . $imageWithTag . ' -f docker/Dockerfile .');
$migrationsImageWithTag =  'automation-platform-svc-migrations:' . $tag;
passthru('docker build -t ' . $migrationsImageWithTag . ' -f docker/migrations.Dockerfile .');
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
file_put_contents(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override);
