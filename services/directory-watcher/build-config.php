<?php

use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\GenerateKustomizeOverride;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\KustomizeApply;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\KustomizeOverride;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Ramona\AutomationPlatformLibBuild\Docker\TargetGenerator as DockerTargetGenerator;

return static function (BuildDefinitionBuilder $builder) {
    $rustTargetGenerator = new TargetGenerator(__DIR__);

    $builder->addTargetGenerator($rustTargetGenerator);

    $builder->addTarget('put-runtime-config', new PutRuntimeConfiguration('runtime.configuration.json'));

    $dockerTargetGenerator = new DockerTargetGenerator(
        __DIR__,
        'image-service',
        'automation-platform-svc-directory-watcher',
        [
            new TargetId(__DIR__, 'put-runtime-config')
        ],
        '../../',
        'docker/Dockerfile'
    );
    $builder->addTargetGenerator($dockerTargetGenerator);

    $dockerMigrationsTargetGenerator = new DockerTargetGenerator(
        __DIR__,
        'image-migrations',
        'automation-platform-svc-migrations',
        [],
        '.',
        'docker/migrations.Dockerfile'
    );
    $builder->addTargetGenerator($dockerMigrationsTargetGenerator);

    $builder->addTarget(
        'generate-kustomize-override',
        new GenerateKustomizeOverride(
            'k8s/base/deployment.yaml',
            'k8s/overlays/dev/',
            [
                new KustomizeOverride(
                    '$.spec.template.metadata.labels.app',
                    fn() => 'svc-directory-watcher'
                ),
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
            $dockerTargetGenerator->defaultTargetIds(DefaultTargetKind::Build),
            $dockerMigrationsTargetGenerator->defaultTargetIds(DefaultTargetKind::Build),
        )
    );

    $builder->addTarget(
            'deploy',
            new KustomizeApply('k8s/overlays/dev'),
            array_merge([new TargetId(__DIR__.'/../events/', 'deploy')], $dockerMigrationsTargetGenerator->defaultTargetIds(DefaultTargetKind::Build))
    );

    $builder->addDefaultTarget(
        DefaultTargetKind::Build,
        [
            new TargetId(__DIR__, 'generate-kustomize-override'),
            new TargetId(__DIR__.'/../../libraries/rust/platform/', 'build'),
        ]
    );

    $builder->addDefaultTarget(
        DefaultTargetKind::Fix
    );
};
