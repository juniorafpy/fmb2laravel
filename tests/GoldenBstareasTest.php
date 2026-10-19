<?php

declare(strict_types=1);

namespace Fmb2Laravel\Tests;

use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecord;
use Fmb2Laravel\Fmb\CodeRecordScanner;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Fmb\ScanOutcome;
use PHPUnit\Framework\TestCase;

/**
 * Validacion cruzada contra el catalogo generado por el prototipo Python
 * (fmb_parse.py / build_catalog.py) sobre un formulario real de produccion.
 *
 * Estos archivos viven en el proyecto privado del autor: si no estan
 * disponibles, el test se salta (en el repo publico no existe informacion
 * de la empresa).
 */
final class GoldenBstareasTest extends TestCase
{
    public function test_extraccion_coincide_con_el_catalogo_de_referencia(): void
    {
        $fmbPath = $this->locateFmb();

        if ($fmbPath === null) {
            $this->markTestSkipped(
                'Golden test local no disponible: define FMB2LARAVEL_GOLDEN_FMB '.
                'o coloca forms/bstareas.fmb junto al repo.'
            );
        }

        $catalogDir = $this->locateCatalog($fmbPath);

        if ($catalogDir === null) {
            $this->markTestSkipped('No se encontro el directorio catalog del proyecto orcl-lara.');
        }

        $fmb = FmbFile::open($fmbPath);
        $outcome = (new CodeRecordScanner($fmb->data))->scan();
        $stem = $fmb->stem();

        $expectedTriggers = $this->readJson($catalogDir.DIRECTORY_SEPARATOR.$stem.'.triggers.json');

        // Salvaguarda: el catalogo de referencia tiene contenido real (no pasar
        // trivialmente si algun dia queda vacio).
        $this->assertGreaterThan(150, count($expectedTriggers));
        $this->assertGreaterThan(150, count($outcome->triggers));

        $this->compareRecordList(
            $expectedTriggers,
            $outcome->triggers,
            'triggers',
        );

        $this->compareRecordList(
            $this->readJson($catalogDir.DIRECTORY_SEPARATOR.$stem.'.program_units.json'),
            $outcome->programUnits,
            'program units',
        );

        $expectedConflicts = $this->readJson($catalogDir.DIRECTORY_SEPARATOR.$stem.'.conflicts.json');
        $this->assertSame(
            array_column($expectedConflicts, 'name'),
            array_column($outcome->conflicts, 'name'),
            'conflictos detectados',
        );

        $this->compareReglas($catalogDir, $stem, $outcome);
    }

    /**
     * @param  array<int, array<string, mixed>>  $expected
     * @param  array<string, CodeRecord>  $actual
     */
    private function compareRecordList(array $expected, array $actual, string $label): void
    {
        $mismatches = [];

        if (count($expected) !== count($actual)) {
            $mismatches[] = sprintf('cantidad: esperado %d, actual %d', count($expected), count($actual));
        }

        foreach ($expected as $entry) {
            $name = $entry['name'];

            if (! isset($actual[$name])) {
                $mismatches[] = "falta: {$name}";

                continue;
            }

            $record = $actual[$name];

            foreach (['pid', 'modified', 'versions'] as $field) {
                if ($entry[$field] !== $record->{$field}) {
                    $mismatches[] = "{$name}: campo {$field} (esperado ".json_encode($entry[$field]).', actual '.json_encode($record->{$field}).')';
                }
            }

            if ($entry['source'] !== $record->source) {
                $mismatches[] = "{$name}: source difiere (esperado ".strlen($entry['source']).
                    ' bytes, actual '.strlen($record->source).' bytes)';
            }
        }

        $this->assertSame([], $mismatches, "Diferencias en {$label} contra el catalogo de referencia");
    }

    private function compareReglas(string $catalogDir, string $stem, ScanOutcome $outcome): void
    {
        $reglasPath = $catalogDir.DIRECTORY_SEPARATOR.$stem.'.reglas.json';

        if (! is_file($reglasPath)) {
            return;
        }

        $expected = $this->readJson($reglasPath);

        $analyzer = new RuleAnalyzer([
            'B_TAREAS' => 'TAREAS',
            'B_AFECTADA' => 'BS.TAREA_AFECTADA',
        ]);

        $actual = $analyzer->build($stem, $outcome);

        $mismatches = [];

        if ($expected['form'] !== $actual['form']) {
            $mismatches[] = 'form difiere';
        }

        if ($expected['tablas_globales'] !== $actual['tablas_globales']) {
            $mismatches[] = 'tablas_globales difiere: esperado '.
                json_encode($expected['tablas_globales'], JSON_UNESCAPED_UNICODE).' actual '.
                json_encode($actual['tablas_globales'], JSON_UNESCAPED_UNICODE);
        }

        if ($expected['boilerplate'] !== $actual['boilerplate']) {
            $mismatches[] = 'boilerplate difiere: esperado '.count($expected['boilerplate']).
                ' actual '.count($actual['boilerplate']);
        }

        $expectedBlocks = array_keys($expected['bloques']);
        $actualBlocks = array_keys($actual['bloques']);
        sort($expectedBlocks);
        sort($actualBlocks);

        if ($expectedBlocks !== $actualBlocks) {
            $mismatches[] = 'bloques difieren: esperado '.implode(',', $expectedBlocks).
                ' actual '.implode(',', $actualBlocks);
        }

        foreach ($expected['bloques'] as $block => $expectedBlock) {
            if (! isset($actual['bloques'][$block])) {
                continue;
            }

            $actualBlock = $actual['bloques'][$block];

            if (($expectedBlock['tabla'] ?? null) !== ($actualBlock['tabla'] ?? null)) {
                $mismatches[] = "bloque {$block}: tabla difiere";
            }

            $mismatches = [
                ...$mismatches,
                ...$this->compareEntries(
                    $expectedBlock['triggers_bloque'],
                    $actualBlock['triggers_bloque'],
                    "bloque {$block} (triggers_bloque)",
                ),
            ];

            $expectedItems = array_keys($expectedBlock['items']);
            $actualItems = array_keys($actualBlock['items']);
            sort($expectedItems);
            sort($actualItems);

            if ($expectedItems !== $actualItems) {
                $mismatches[] = "bloque {$block}: items difieren (esperado ".
                    count($expectedItems).' actual '.count($actualItems).')';
            }

            foreach ($expectedBlock['items'] as $item => $entries) {
                if (! isset($actualBlock['items'][$item])) {
                    continue;
                }

                $mismatches = [
                    ...$mismatches,
                    ...$this->compareEntries(
                        $entries,
                        $actualBlock['items'][$item],
                        "item {$block}.{$item}",
                    ),
                ];
            }
        }

        $this->assertSame(
            [],
            array_slice($mismatches, 0, 10),
            'Diferencias en reglas contra el catalogo de referencia',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $expected
     * @param  array<int, array<string, mixed>>  $actual
     * @return array<int, string>
     */
    private function compareEntries(array $expected, array $actual, string $path): array
    {
        $mismatches = [];

        if (count($expected) !== count($actual)) {
            $mismatches[] = "{$path}: cantidad de entradas difiere";

            return $mismatches;
        }

        $actualByName = [];

        foreach ($actual as $entry) {
            $actualByName[$entry['nombre']] = $entry;
        }

        foreach ($expected as $entry) {
            $name = $entry['nombre'];

            if (! isset($actualByName[$name])) {
                $mismatches[] = "{$path}: falta {$name}";

                continue;
            }

            $actualEntry = $actualByName[$name];

            foreach (['tipo', 'modificado', 'tablas', 'escribe', 'mensajes', 'llamadas', 'items_afectados'] as $field) {
                if (($entry[$field] ?? null) !== ($actualEntry[$field] ?? null)) {
                    $mismatches[] = "{$path} → {$name}: campo {$field} difiere (esperado ".
                        json_encode($entry[$field] ?? null, JSON_UNESCAPED_UNICODE).', actual '.
                        json_encode($actualEntry[$field] ?? null, JSON_UNESCAPED_UNICODE).')';
                }
            }

            if (($entry['codigo'] ?? null) !== ($actualEntry['codigo'] ?? null)) {
                $mismatches[] = "{$path} → {$name}: codigo difiere";
            }
        }

        return $mismatches;
    }

    private function locateFmb(): ?string
    {
        $candidates = [
            getenv('FMB2LARAVEL_GOLDEN_FMB') ?: null,
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'orcl-lara'.DIRECTORY_SEPARATOR.'forms'.DIRECTORY_SEPARATOR.'bstareas.fmb',
            dirname(__DIR__).DIRECTORY_SEPARATOR.'forms'.DIRECTORY_SEPARATOR.'bstareas.fmb',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function locateCatalog(string $fmbPath): ?string
    {
        $candidates = [
            getenv('FMB2LARAVEL_GOLDEN_CATALOG') ?: null,
            dirname(dirname($fmbPath)).DIRECTORY_SEPARATOR.'catalog',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            $this->markTestSkipped("Falta el archivo de referencia: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded, "JSON invalido: {$path}");

        return $decoded;
    }
}
