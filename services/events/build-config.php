<?php

use Ramona\AutomationPlatformLibBuild\Actions\Docker\BuildDockerImage;
use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Actions\KustomizeApply;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;
use Ramona\AutomationPlatformLibBuild\Targets\Target;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;


return static function (BuildDefinitionBuilder $builder) {
    $rustTargetGenerator = new TargetGenerator(__DIR__);

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

    $builder->addTargetGenerator($rustTargetGenerator);

    $dockerTargetGenerator = new \Ramona\AutomationPlatformLibBuild\Docker\TargetGenerator(__DIR__, 'image-service', 'automation-platform-svc-events', [], '../../', 'docker/Dockerfile');
    $builder->addTargetGenerator($dockerTargetGenerator);
    $dockerMigrationsTargetGenerator = new \Ramona\AutomationPlatformLibBuild\Docker\TargetGenerator(__DIR__, 'image-migrations', 'ap-svc-events-migrations', [], '.', 'docker/migrations.Dockerfile');
    $builder->addTargetGenerator($dockerMigrationsTargetGenerator);

    $builder->addTarget(
        new Target(
            'generate-kustomize-override',
            new PutFile(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override),
            array_merge(
                $dockerTargetGenerator->defaultTargetIds(DefaultTargetKind::Build),
                $dockerMigrationsTargetGenerator->defaultTargetIds(DefaultTargetKind::Build),
            )
        )
    );
    $builder->addTarget(
        new Target(
            'deploy',
            new KustomizeApply('k8s/overlays/dev'),
            [new TargetId(__DIR__, 'build')]
        )
    );

    $builder->addDefaultTarget(
        DefaultTargetKind::Build,
        [
            new TargetId(__DIR__, 'generate-kustomize-override'),
        ]
    );

    $builder->addDefaultTarget(
        DefaultTargetKind::Fix
    );
};