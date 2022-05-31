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
    $agents = glob(__DIR__ . '/agents/*');

    $builder->addTarget(
        'build',
        new NoOp(),
        array_map(
            static fn(string $path) => new TargetId($path, 'build'),
            array_merge($tools, $libraries, $services, $agents)
        )
    );

    $builder->addTarget(
        'fix',
        new NoOp(),
        array_map(
            static fn(string $path) => new TargetId($path, 'fix'),
            array_merge($tools, $libraries, $services, $agents)
        )
    );

    $builder->addTarget(
        'build-k8s-mounts',
        new PutFiles(function (Context $context) {
            $result = [];
            $mountNames = [];
            foreach ($context->configuration()->getSingleBuildValue('$.kubernetes.mounts') as $mountDescription) {
                $mountNames[] = $mountDescription['name'];

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

                $result['k8s/base/' . $mountDescription['name'] . '--claim.yaml'] =
                    <<<EOF
                    apiVersion: v1
                    kind: PersistentVolumeClaim
                    
                    metadata:
                      name: {$mountDescription['name']}--claim
                    spec:
                      storageClassName: ""
                      volumeName: {$mountDescription['name']}
                      accessModes:
                        - ReadWriteMany
                      resources:
                        requests:
                          storage: {$mountDescription['spec']['capacity']['storage']}
                    EOF;
            }

            $resources = [];
            foreach($mountNames as $mountName) {
                $resources[] = '  - ' . $mountName . '.yaml';
                $resources[] = '  - ' . $mountName . '--claim.yaml';
            }

            $resources = implode(PHP_EOL, $resources);

            $result['k8s/base/kustomization.yaml'] =
                <<<EOF
                apiVersion: kustomize.config.k8s.io/v1beta1
                resources:
                {$resources}
                EOF;

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
