<?php

$tag = str_replace('.', '', uniqid('', true));
$imageWithTag =  'automation-platform-svc-events:' . $tag;
passthru('docker build -t ' . $imageWithTag . ' -f docker/Dockerfile .');
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
file_put_contents(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override);
