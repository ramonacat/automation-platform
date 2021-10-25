<?php

namespace Ramona\AutomationPlatformLibBuild;
use function Safe\chdir;
use function Safe\getcwd;

final class BuildExecutor
{
    private array $executedTargets = [];
    private DependencyQueue $leftToRun;

    public function __construct(private BuildDefinitions $buildDefinitions)
    {
        $this->leftToRun = new DependencyQueue();
    }

    public function executeTarget(string $workingDirectory, string $name): BuildActionResult
    {
        $this->leftToRun->push(new Dependency($workingDirectory, $name));
        return $this->runQueue();
    }

    private function runQueue(): BuildActionResult
    {
        // todo the queue building should be separate from execution, so a cyclic dependency check can be done
        while(!$this->leftToRun->isEmpty()) {
            $currentDependency = $this->leftToRun->pop();

            $target = $this->buildDefinitions
                ->get($currentDependency->path())
                ->target($currentDependency->target());

            $dependencies = $target->dependencies();

            $allDependenciesExecuted = true;

            foreach($dependencies as $dependency) {
                $dependencyKey = $dependency->id();
                if(!isset($this->executedTargets[$dependencyKey])) {
                    $this->leftToRun->push($dependency);

                    $allDependenciesExecuted = false;
                }
            }

            if($allDependenciesExecuted) {
                $workingDirectory = getcwd();
                chdir($currentDependency->path());
                try {
                    $result = $target->execute();
                } finally {
                    chdir($workingDirectory);
                }

                if(!$result->hasSucceeded())
                {
                    return $result;
                }

                $this->executedTargets[$currentDependency->id()] = true;
            } else {
                $this->leftToRun->push($currentDependency);
            }
        }

        return BuildActionResult::ok();
    }
}