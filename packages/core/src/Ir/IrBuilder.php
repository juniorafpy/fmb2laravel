<?php

declare(strict_types=1);

namespace Fmb2Laravel\Ir;

use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecord;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Fmb\ScanOutcome;
use Fmb2Laravel\Fmb\TriggerKind;
use Fmb2Laravel\Version;

/**
 * Construye la representacion intermedia (IR) del formulario a partir del
 * escaneo del .FMB mas el analisis de reglas. La IR es la entrada del
 * generador Laravel/Filament (v0.2) y el contrato estable del proyecto.
 */
final class IrBuilder
{
    public function __construct(private readonly RuleAnalyzer $analyzer) {}

    public function build(FmbFile $file, ScanOutcome $outcome, string $moduleName): array
    {
        $blocks = [];
        $tables = [];
        $written = [];
        $messages = [];

        $triggers = [];
        $id = 0;

        foreach ($outcome->triggers as $record) {
            $triggers[] = $this->entry($id++, $record, $blocks, $tables, $written, $messages);
        }

        foreach ($outcome->programUnits as $record) {
            $triggers[] = $this->entry($id++, $record, $blocks, $tables, $written, $messages);
        }

        $tables = array_keys($tables);
        $written = array_keys($written);
        $messages = array_keys($messages);

        ksort($blocks);
        sort($tables);
        sort($written);
        sort($messages);

        $itemCount = 0;
        $blockList = array_values(array_map(
            static function (array $block) use (&$itemCount): array {
                $items = array_values($block['items']);
                $itemCount += count($items);

                return [
                    'name' => $block['name'],
                    'candidateTable' => $block['candidateTable'],
                    'triggers' => $block['triggers'],
                    'items' => $items,
                    'triggerCount' => count($block['triggers']),
                    'itemCount' => count($items),
                ];
            },
            $blocks,
        ));

        return [
            'irVersion' => '1.0',
            'generator' => [
                'name' => Version::NAME,
                'version' => Version::VERSION,
            ],
            'source' => [
                'file' => basename($file->path),
                'sizeBytes' => $file->size(),
                'sha256' => $file->sha256(),
            ],
            'module' => [
                'name' => $moduleName,
            ],
            'stats' => [
                'records' => $outcome->totalRecords,
                'triggers' => count($outcome->triggers),
                'programUnits' => count($outcome->programUnits),
                'conflicts' => count($outcome->conflicts),
                'blocks' => count($blocks),
                'items' => $itemCount,
            ],
            'blocks' => $blockList,
            'triggers' => $triggers,
            'conflicts' => $outcome->conflicts,
            'tables' => [
                'referenced' => $tables,
                'written' => $written,
            ],
            'messages' => $messages,
            'warnings' => $this->warnings($outcome),
        ];
    }

    public function encode(array $ir): string
    {
        return json_encode(
            $ir,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        )."\n";
    }

    /**
     * @param  array<string, array<string, mixed>>  $blocks
     * @param  array<string, bool>  $tables
     * @param  array<string, bool>  $written
     * @param  array<string, bool>  $messages
     */
    private function entry(
        int $id,
        CodeRecord $record,
        array &$blocks,
        array &$tables,
        array &$written,
        array &$messages,
    ): array {
        $analysis = $this->analyzer->analyzeSource($record->source);

        foreach ($analysis['tablas'] as $table) {
            $tables[$table] = true;
        }

        foreach ($analysis['escribe_tabla'] as $table) {
            $written[$table] = true;
        }

        foreach ($analysis['mensajes'] as $message) {
            $messages[$message] = true;
        }

        $scope = TriggerKind::scope($record->name);
        $block = null;
        $item = null;

        if ($scope !== null && $scope !== 'PANTALLA') {
            $parts = explode('.', $scope, 2);
            $block = $parts[0];
            $item = $parts[1] ?? null;
        }

        if ($block !== null) {
            if (! isset($blocks[$block])) {
                $blocks[$block] = [
                    'name' => $block,
                    'candidateTable' => $this->analyzer->tableForBlock($block),
                    'triggers' => [],
                    'items' => [],
                ];
            }

            if ($item !== null) {
                if (! isset($blocks[$block]['items'][$item])) {
                    $blocks[$block]['items'][$item] = ['name' => $item, 'triggers' => []];
                }

                $blocks[$block]['items'][$item]['triggers'][] = $id;
            } else {
                $blocks[$block]['triggers'][] = $id;
            }
        }

        return [
            'id' => $id,
            'name' => $record->name,
            'type' => TriggerKind::type($record->name),
            'scope' => $scope,
            'block' => $block,
            'item' => $item,
            'modified' => $record->modified,
            'pid' => $record->pid,
            'versions' => $record->versions,
            'lines' => $record->lines(),
            'classification' => $this->analyzer->classify($record->source),
            'readsTables' => $analysis['tablas'],
            'writesTables' => $analysis['escribe_tabla'],
            'messages' => $analysis['mensajes'],
            'itemsReferenced' => $analysis['items'],
            'calls' => $analysis['llamadas'],
            'source' => $record->source,
        ];
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    private function warnings(ScanOutcome $outcome): array
    {
        $warnings = [];

        if ($outcome->conflicts !== []) {
            $names = implode(', ', array_column($outcome->conflicts, 'name'));

            $warnings[] = [
                'code' => 'VERSION-CONFLICT',
                'message' => count($outcome->conflicts).' objeto(s) con versiones divergentes; se conservo la mas reciente: '.$names,
            ];
        }

        $warnings[] = [
            'code' => 'HEURISTIC-LIMITS',
            'message' => 'Lectura heuristica del binario: los objetos cuyo codigo no empieza con BEGIN/DECLARE (p. ej. POST-QUERY iniciado en comentario) pueden requerir revision manual del pool de cadenas.',
        ];

        $warnings[] = [
            'code' => 'NO-FORMS-VERSION',
            'message' => 'No se pudo determinar la version exacta de Forms con la que se compilo el modulo; los catalogos se generan con el formato validado en Forms 6i.',
        ];

        return $warnings;
    }
}
