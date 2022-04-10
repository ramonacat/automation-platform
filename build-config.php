<?php

use Ramona\AutomationPlatformLibBuild\Actions\BuildAction;
use Ramona\AutomationPlatformLibBuild\Actions\Kubernetes\KustomizeApply;
use Ramona\AutomationPlatformLibBuild\Actions\NoOp;
use Ramona\AutomationPlatformLibBuild\Actions\PutFiles;
use Ramona\AutomationPlatformLibBuild\BuildResult;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Output\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Targets\TargetId;
use Symfony\Component\Yaml\Yaml;

return static function (BuildDefinitionBuilder $builder) {
    $services = glob(__DIR__ . '/services/*');
    $libraries = glob(__DIR__ . '/libraries/*/*');
    $tools = glob(__DIR__ . '/tools/*');

    $builder->addTarget(
        'build',
        new NoOp(),
        array_map(
            static fn(string $path) => new TargetId($path, 'build'),
            array_merge($tools, $libraries, $services)
        )
    );

    $builder->addTarget(
        'fix',
        new NoOp(),
        array_map(
            static fn(string $path) => new TargetId($path, 'fix'),
            array_merge($tools, $libraries, $services)
        )
    );

    $builder->addTarget(
        'build-k8s-mounts',
        new PutFiles(function (Context $context) {
            $resources = array_map(
                static fn(array $mountDescription) => '  - ' . $mountDescription['name'] . '.yaml',
                $context->configuration()->getSingleBuildValue('$.kubernetes.mounts')
            );
            $resources = implode(PHP_EOL, $resources);
            return [
                'k8s/base/kustomization.yaml' =>
                    <<<EOF
                    apiVersion: kustomize.config.k8s.io/v1beta1
                    resources:
                    {$resources}
                    EOF
                ];
        }
        ),
        [new TargetId(__DIR__, 'build-k8s-mounts-2')]
    );

    $builder->addTarget(
        'build-k8s-mounts-2',
        new PutFiles(function (Context $context) {
            $result = [];
            foreach ($context->configuration()->getSingleBuildValue('$.kubernetes.mounts') as $mountDescription) {
                $spec = Yaml::dump($mountDescription['spec'], 0, 2);

                $result['k8s/base/' . $mountDescription['name'] . '.yaml'] =
                    <<<EOF
                        apiVersion: v1
                        kind: PersistentVolume
                        
                        metadata:
                          name: {$mountDescription['name']}
                        spec: {$spec}
                        EOF
                ;
            }

            return $result;
        })
    );

    $builder->addTarget(
        'deploy-k8s-mounts',
        new KustomizeApply('k8s/base'),
        [
            new TargetId(__DIR__, 'build-k8s-mounts'),
        ]
    );

    $builder->addTarget(
        'deploy',
        new NoOp(),
        array_merge(
            [new TargetId(__DIR__, 'build')],
            [new TargetId(__DIR__, 'deploy-k8s-mounts')],
            array_map(
                static fn(string $path) => new TargetId($path, 'deploy'),
                $services
            )
        )
    );
};