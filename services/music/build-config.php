<?php

use Ramona\AutomationPlatformLibBuild\Actions\Docker\BuildNixifiedDockerImage;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\GenerateKustomizeOverride;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\KustomizeApply;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\KustomizeOverride;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Targets\DefaultTargetKind;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;

return static function (BuildDefinitionBuilder $builder) {
    $builder->addRustTargetGenerator();

    $builder->addTarget('put-runtime-config', new PutRuntimeConfiguration('runtime.configuration.json'));

    $builder->addTarget(
        'image-service-docker-build',
        new BuildNixifiedDockerImage('image-service', 'svc-music'),
        [new TargetId(__DIR__, 'put-runtime-config')]
    );
    
    $builder->addTarget(
        'image-migrations-docker-build',
        new BuildNixifiedDockerImage('image-migrations', 'svc-music-migrations', nixFilePath: 'docker/migrations.nix'),
        [new TargetId(__DIR__, 'put-runtime-config')]
    );

    // todo this is copy-pasted from directory-watcher, there should be something more generalised for this
    $builder->addTarget(
        'generate-kustomize-override',
        new GenerateKustomizeOverride(
            'k8s/base/deployment.yaml',
            'k8s/overlays/dev/',
            [
                new KustomizeOverride(
                    '$.spec.template.metadata.labels.app',
                    fn() => 'svc-music'
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
                        'image' => $c->artifactCollector()->getByKey(__DIR__, 'image-service')->name(),
                    ] + 
                    (count($c->configuration()->getSingleBuildValueOrDefault('$.kubernetes.mounts', [])) > 0 
                    ? [
                            'volumeMounts' => [
                                ...array_map(
                                    static fn(array $mount) => [
                                        'name' => $mount['name'],
                                        'mountPath' => '/mnt/' . $mount['name'],
                                    ],
                                    $c->configuration()->getSingleBuildValueOrDefault('$.kubernetes.mounts', [])
                                )
                            ]
                    ] 
                    : [])
                ),
                new KustomizeOverride(
                    '$.spec.template.spec.volumes',
                    fn(Context $c) => [
                        [
                            'name' => 'music-ap-music-credentials',
                            'secret' => [
                                'secretName' => 'music.ap-music.credentials.postgresql.acid.zalan.do'
                            ]
                        ],
                        ...array_map(
                            static fn(array $mount) => [
                                'name' => $mount['name'],
                                'persistentVolumeClaim' => [
                                    'claimName' => $mount['name'] . '--claim'
                                ]
                            ],
                            $c->configuration()->getSingleBuildValueOrDefault('$.kubernetes.mounts', [])
                        )
                    ]
                )
            ]
        ),
        [
            new TargetId(__DIR__, 'image-service-docker-build'),
            new TargetId(__DIR__, 'image-migrations-docker-build'),
        ],
    );

    $builder->addTarget(
            'deploy',
            new KustomizeApply('k8s/overlays/dev'),
            [new TargetId(__DIR__.'/../events/', 'deploy')]
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
