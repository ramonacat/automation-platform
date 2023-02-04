<?php

use Ramona\AutomationPlatformLibBuild\Actions\Docker\BuildNixifiedDockerImage;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\GenerateKustomizeOverride;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\KustomizeApply;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\KustomizeOverride;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;


return static function (BuildDefinitionBuilder $builder) {
    $builder->addRustTargetGenerator();

    $builder->addTarget(
        'image-service-docker-build',
        new BuildNixifiedDockerImage('image-service', 'svc-events')
    );

    $dockerMigrationsTargetGenerator = new \Ramona\AutomationPlatformLibBuild\Docker\TargetGenerator(__DIR__, 'image-migrations', 'ap-svc-events-migrations', [], '.', 'docker/migrations.Dockerfile');
    $builder->addTargetGenerator($dockerMigrationsTargetGenerator);

    $builder->addTarget(
        'generate-kustomize-override',
        new GenerateKustomizeOverride(
            'k8s/base/deployment.yaml',
            'k8s/overlays/dev',
            [
                new KustomizeOverride('$.spec.template.metadata.labels.app', fn() => 'svc-events'),
                new KustomizeOverride(
                    '$.spec.template.spec.initContainers[0]',
                    fn(Context $c) => [
                        'name' => 'migrations',
                        'image' => $c->artifactCollector()->getByKey(__DIR__, 'image-migrations')->name()
                    ]
                ),
                new KustomizeOverride(
                    '$.spec.template.spec.containers[0]',
                    fn(Context $c) => [
                        'name' => 'app',
                        'image' => $c->artifactCollector()->getByKey(__DIR__, 'image-service')->name()
                    ]
                ),
            ]
        ),
        array_merge(
            [new TargetId(__DIR__, 'image-service-docker-build')],
            $dockerMigrationsTargetGenerator->defaultTargetIds(DefaultTargetKind::Build),
        )
    );

    $builder->addTarget(
        'deploy',
        new KustomizeApply('k8s/overlays/dev'),
        [new TargetId(__DIR__, 'build')]
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
