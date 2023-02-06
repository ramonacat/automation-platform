<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CodeCoverage;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use function count;
use Exception;
use function file_put_contents;
use function is_array;
use function is_object;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function number_format;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\Artifacts\UnexpectedArtifactType;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Filesystem\Filesystem;
use Ramona\AutomationPlatformLibBuild\Git;
use RuntimeException;
use function simplexml_load_string;
use SimpleXMLElement;
use Webmozart\PathUtil\Path;

final class Publisher implements \Ramona\AutomationPlatformLibBuild\Artifacts\Publisher
{
    public function __construct(
        private readonly Git $git,
        private readonly Filesystem $filesystem,
        private readonly State $state,
    ) {
    }

    public function publishes(): string
    {
        return Artifact::class;
    }

    public function publish(\Ramona\AutomationPlatformLibBuild\Artifacts\Artifact $artifact): void
    {
        if (!($artifact instanceof Artifact)) {
            throw UnexpectedArtifactType::fromArtifact(Artifact::class, $artifact);
        }

        match ($artifact->kind()) {
            Kind::LlvmJson => $this->publishLlvmJson($artifact->name()),
            Kind::Clover => $this->publishClover($artifact->name()),
        };
    }

    private function publishLlvmJson(string $path): void
    {
        $contents = $this->filesystem->readFile($path);
        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw InvalidCoverageFile::cannotDecode($path);
        }
        
        if (!isset($data['data'][0]['totals']) || !is_array($data['data'][0]['totals'])) {
            throw InvalidCoverageFile::noKeyInFile($path, 'data[0].totals');
        }

        $totals = (array)$data['data'][0]['totals'];

        if (!isset($totals['lines']) || !is_array($totals['lines'])) {
            throw InvalidCoverageFile::noKeyInFile($path, 'data[0].totals.lines');
        }

        if (isset($totals['lines']['percent'])) {
            $coverage = (float)$totals['lines']['percent'] / 100.0;
        } else {
            $coverage = 0;
        }

        $this->state->addEntry(Path::makeRelative($path, $this->git->repositoryRoot()), $coverage);
    }

    private function publishClover(string $path): void
    {
        // TODO stream the file instead
        $xml = simplexml_load_string($this->filesystem->readFile($path));
        
        if ($xml === false) {
            throw new RuntimeException('Could not load XML file: ' . $path);
        }

        if (!isset($xml->project) || !is_object($xml->project)) {
            throw new RuntimeException('Could not find project in XML file: ' . $path);
        }

        if (!isset($xml->project->metrics) || !($xml->project->metrics instanceof SimpleXMLElement)) {
            throw new RuntimeException('Could not find metrics in XML file: ' . $path);
        }

        if (!isset($xml->project->metrics['statements'])) {
            throw new RuntimeException('Could not find statements in XML file: ' . $path);
        }

        if (!isset($xml->project->metrics['coveredstatements'])) {
            throw new RuntimeException('Could not find coveredstatements in XML file: ' . $path);
        }

        $statements = (int)($xml->project->metrics['statements']);
        $coveredStatements = (int)($xml->project->metrics['coveredstatements']);

        if ($statements === 0) {
            $coverage = 0;
        } else {
            $coverage = $coveredStatements / $statements;
        }

        $this->state->addEntry(Path::makeRelative($path, $this->git->repositoryRoot()), $coverage);
    }

    public const COVERAGE_PATH = __DIR__ . '/../../../../.build/coverage.json';

    /**
     * @param Ansi $ansi
     */
    public function print(Ansi $ansi, Context $context): void
    {
        $ciState = $context->buildFacts()->ciState();

        if ($ciState !== null && $ciState->actor() === 'dependabot[bot]') {
            $ansi
                ->color([SGR::COLOR_FG_YELLOW])
                ->bold()
                ->text('! Skipping code coverage for dependabot.' . PHP_EOL)
                ->nostyle();
            return;
        }

        if ($ciState !== null && $ciState->currentRef() === 'origin/main') {
            $ansi
                ->color([SGR::COLOR_FG_YELLOW])
                ->bold()
                ->text('! Skipping code coverage for main branch.' . PHP_EOL)
                ->nostyle();
            return;
        }

        $ansi
            ->color([SGR::COLOR_FG_YELLOW])
            ->bold()
            ->text('Code Coverage:' . PHP_EOL)
            ->nostyle();

        $baseBranch = $ciState === null ? 'origin/main' : $ciState->baseRef();
        $baseRef = $this->git->runGit(['git', 'rev-parse', $baseBranch]);

        try {
            /** @var array<string, float>|false|null $originalCoverage */
            $originalCoverage = json_decode($this->git->runGit(['git', 'show', $baseRef . ':.build/coverage.json']), true);
        } catch (Exception) {
            $ansi
                ->color([SGR::COLOR_FG_RED])
                ->bold()
                ->text('! Could not get original coverage, assuming no coverage on main branch.' . PHP_EOL)
                ->nostyle();

            $originalCoverage = [];
        }

        if ($originalCoverage === false || $originalCoverage === null) {
            $originalCoverage = [];
        }

        $negativeCoverageChanges = [];
        $positiveCoverageChanges = [];

        foreach ($this->state->coverages() as $path => $coverage) {
            if (!isset($originalCoverage[$path])) {
                $coverageChange = $coverage;
            } else {
                $coverageChange = $coverage - $originalCoverage[$path];
            }

            if ($coverageChange < 0.0) {
                $negativeCoverageChanges[$path] = $coverageChange;
            } elseif ($coverageChange > 0.001) {
                $positiveCoverageChanges[$path] = $coverageChange;
            }

            $ansi->text('    ' . $path . ': ' . number_format($coverage * 100.0, 2) . '% (' . number_format($coverageChange * 100.0, 2) . '%)' . PHP_EOL);

            unset($originalCoverage[$path]);
        }

        $missingCoverage = $originalCoverage;

        foreach ($originalCoverage as $path => $coverage) {
            $ansi->text('    ' . $path . ': ' . number_format($coverage * 100.0, 2) . '%' . PHP_EOL);
        }

        if (count($negativeCoverageChanges) > 0) {
            $ansi
                ->color([SGR::COLOR_FG_RED])
                ->bold()
                ->text('! Negative coverage changes:' . PHP_EOL)
                ->nostyle();

            foreach ($negativeCoverageChanges as $path => $coverageChange) {
                $ansi->text('    ' . $path . ': ' . number_format($coverageChange * 100.0, 2) . '%' . PHP_EOL);
            }

            throw new RuntimeException('Negative coverage changes');
        }

        if (count($missingCoverage) > 0) {
            $ansi
                ->color([SGR::COLOR_FG_RED])
                ->bold()
                ->text('! Missing coverage:' . PHP_EOL)
                ->nostyle();

            foreach ($missingCoverage as $path => $coverage) {
                $ansi->text('    ' . $path . ': ' . number_format($coverage * 100.0, 2) . '%' . PHP_EOL);
            }

            throw new RuntimeException('Missing coverage');
        }

        if (count($positiveCoverageChanges) === 0) {
            $ansi
                ->color([SGR::COLOR_FG_RED])
                ->bold()
                ->text('! No positive coverage changes' . PHP_EOL)
                ->nostyle();

            throw new RuntimeException('No positive coverage changes');
        } else {
            $ansi
                ->color([SGR::COLOR_FG_GREEN])
                ->bold()
                ->text('Positive coverage changes:' . PHP_EOL)
                ->nostyle();

            foreach ($positiveCoverageChanges as $path => $coverageChange) {
                $ansi->text('    ' . $path . ': ' . number_format($coverageChange * 100.0, 2) . '%' . PHP_EOL);
            }
        }

        if (count($this->state->coverages()) > 0) {
            $encodedCoverages = json_encode($this->state->coverages(), JSON_PRETTY_PRINT);

            if ($encodedCoverages === false) {
                throw new RuntimeException('Could not encode coverages');
            }

            file_put_contents(self::COVERAGE_PATH, $encodedCoverages);
        }
    }
}
