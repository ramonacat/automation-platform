<?php

declare(strict_types=1);

final class ProcessFailed extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forCommand(string $command, int $exitCode): self
    {
        return new self("Failed to execute \"{$command}\" - exit code {$exitCode}");
    }
}

function runProcess(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw ProcessFailed::forCommand($command, $exitCode);
    }
}

function buildImage(string $name, string $path): string
{
    $tag = str_replace('.', '', uniqid('', true));
    $imageWithTag = "automation-platform-{$name}:{$tag}";
    runProcess('docker build -t ' . $imageWithTag . ' -f ' . $path . ' .');
    return $imageWithTag;
}

function usage(string $executableName): void
{
    fprintf(STDERR, 'Usage: %s [build|check|cs-fix]', $executableName);
}

function createDeploymentOverride(string $imageWithTag): void
{
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
    file_put_contents(__DIR__ . '/k8s/overlays/dev/deployment.yaml', $override);
}

if ($argc !== 2) {
    usage($argv[0]);
    exit(1);
}

switch ($argv[1]) {
    case 'build':
        $imageWithTag = buildImage('svc-events', 'docker/Dockerfile');
        createDeploymentOverride($imageWithTag);
        break;
    case 'check':
        runProcess('php vendor/bin/ecs');
        runProcess('php vendor/bin/psalm');
        break;
    case 'cs-fix':
        runProcess('php vendor/bin/ecs --fix');
        break;
}
