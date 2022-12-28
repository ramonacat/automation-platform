<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\CodeCoverage;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use function file_get_contents;
use function is_array;
use function is_object;
use function json_decode;
use function number_format;
use const PHP_EOL;
use RuntimeException;
use function simplexml_load_file;
use SimpleXMLElement;

final class Publisher implements \Ramona\AutomationPlatformLibBuild\Artifacts\Publisher
{
    /**
     * @var array<string, float>
     */
    private array $coverages = [];

    public function __construct(
    ) {
    }

    public function publishes(): string
    {
        return Artifact::class;
    }

    public function publish(\Ramona\AutomationPlatformLibBuild\Artifacts\Artifact $artifact): void
    {
        if (!($artifact instanceof Artifact)) {
            // TODO better exception type
            throw new RuntimeException('Artifact is not of type CodeCoverage\Artifact');
        }

        match ($artifact->kind()) {
            Kind::LlvmJson => $this->publishLlvmJson($artifact->name()),
            Kind::Clover => $this->publishClover($artifact->name()),
        };
    }

    private function publishLlvmJson(string $path): void
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read JSON file: ' . $path);
        }

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new RuntimeException('Could not decode JSON file: ' . $path);
        }
        
        if (!isset($data['data'][0]['totals']) || !is_array($data['data'][0]['totals'])) {
            throw new RuntimeException('Could not find totals in JSON file: ' . $path);
        }

        $totals = (array)$data['data'][0]['totals'];

        if (!isset($totals['lines']) || !is_array($totals['lines'])) {
            throw new RuntimeException('Could not find lines in JSON file: ' . $path);
        }

        if (isset($totals['lines']['percent'])) {
            $coverage = (float)$totals['lines']['percent'] / 100.0;
        } else {
            $coverage = 0;
        }

        $this->coverages[$path] = $coverage;
    }

    private function publishClover(string $path): void
    {
        $xml = simplexml_load_file($path);
        
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

        $this->coverages[$path] = $coverage;
    }
    /**
     * @param Ansi $ansi
     */
    public function print(Ansi $ansi): void
    {
        $ansi
            ->color([SGR::COLOR_FG_YELLOW])
            ->bold()
            ->text('Code Coverage:' . PHP_EOL)
            ->nostyle();

        foreach ($this->coverages as $path => $coverage) {
            $ansi->text('    ' . $path . ': ' . number_format($coverage * 100.0, 2) . '%' . PHP_EOL);
        }
    }
}
