<?php

declare(strict_types=1);

namespace Fmb2Laravel\Catalog;

use Fmb2Laravel\Fmb\ScanOutcome;
use Fmb2Laravel\Fmb\TriggerKind;

/**
 * Analiza el PL/SQL extraido y construye el catalogo de reglas de negocio.
 *
 * Port fiel de build_catalog.py: clasifica boilerplate, detecta tablas
 * leidas/escritas, mensajes, items asignados y llamadas a utilidades,
 * y agrupa todo por bloque/item del formulario.
 */
final class RuleAnalyzer
{
    private const FROM_PATTERN = '/\b(?:from|join)\s+([a-z][a-z0-9_$]*(?:\.[a-z][a-z0-9_$]*)?)/iu';

    private const INTO_PATTERN = '/\binto\s+([a-z][a-z0-9_$]*(?:\.[a-z][a-z0-9_$]*)?)/iu';

    private const INSERT_PATTERN = '/\binsert\s+into\s+([a-z][a-z0-9_$]*(?:\.[a-z][a-z0-9_$]*)?)/iu';

    private const UPDATE_PATTERN = '/\bupdate\s+([a-z][a-z0-9_$]*(?:\.[a-z][a-z0-9_$]*)?)/iu';

    private const DELETE_PATTERN = '/\bdelete\s+from\s+([a-z][a-z0-9_$]*(?:\.[a-z][a-z0-9_$]*)?)/iu';

    private const MESSAGE_PATTERN = "/\b(?:mensaje|alerta|message)\s*\(\s*'([^']{3,300})'/iu";

    private const ASSIGN_PATTERN = '/:([A-Za-z_][\w$]*)\.([A-Za-z_][\w$]*)\s*:=/u';

    private const CALL_PATTERN = '/'
        .'\b(?:permisos|utilitarios|toolbar|nspc1)\.[a-z_][\w$]*'
        .'|\b(?:alerta|mensaje|display_error|envia_mail(?:_inf|_var|_usuario)?|envia_autorizacion'
        .'|habilitar|busca_autorizante_gte_area|busca_parametro|inicializa_globales'
        .'|inicializa_locales|rutina_inicial|check_package_failure|clear_all_master_details'
        .'|query_master_details|find_relation|envia_mail_inf)\b/iu';

    private const PSEUDO_TABLES = ['dual', 'sys.dual', 'system.dual'];

    private const PSEUDO_BLOCKS = ['GLOBAL', 'SYSTEM', 'VARIABLES', 'PARAMETER'];

    /**
     * @param  array<string, string>  $blockTables  mapa bloque => tabla (override del formulario)
     */
    public function __construct(private readonly array $blockTables = []) {}

    public function classify(string $source): string
    {
        if (str_contains($source, "do_key('list_values')") && str_contains($source, 'get_item_property')) {
            return 'boilerplate_lov';
        }

        // Navegacion boilerplate: un LOV_NAME y una asignacion <bloque>.LOV.
        if (str_contains($source, 'LOV_NAME') && preg_match('/\b[a-z_][a-z0-9_$]*\.lov\b/i', $source) === 1) {
            return 'boilerplate_nav';
        }

        return 'logica';
    }

    /**
     * @return array{tablas: array<int, string>, escribe_tabla: array<int, string>, mensajes: array<int, string>, items: array<int, string>, llamadas: array<int, string>}
     */
    public function analyzeSource(string $source): array
    {
        $tables = [];

        foreach ([self::FROM_PATTERN, self::INTO_PATTERN] as $pattern) {
            if (preg_match_all($pattern, $source, $matches) > 0) {
                foreach ($matches[1] as $table) {
                    $tables[strtolower($table)] = true;
                }
            }
        }

        $writes = [];

        foreach ([self::INSERT_PATTERN, self::UPDATE_PATTERN, self::DELETE_PATTERN] as $pattern) {
            if (preg_match_all($pattern, $source, $matches) > 0) {
                foreach ($matches[1] as $table) {
                    $writes[strtoupper($table)] = true;
                }
            }
        }

        $tables = array_keys($tables);
        $tables = array_values(array_filter(
            $tables,
            static fn (string $table): bool => ! in_array($table, self::PSEUDO_TABLES, true)
                && ! str_starts_with($table, ':'),
        ));
        sort($tables);

        $writes = array_keys($writes);
        sort($writes);

        $messages = [];

        if (preg_match_all(self::MESSAGE_PATTERN, $source, $matches) > 0) {
            foreach ($matches[1] as $message) {
                $messages[$message] = true;
            }
        }

        $messages = array_keys($messages);
        sort($messages);

        $items = [];

        if (preg_match_all(self::ASSIGN_PATTERN, $source, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $block = strtoupper($match[1]);
                $item = strtoupper($match[2]);

                if (in_array($block, self::PSEUDO_BLOCKS, true)) {
                    continue;
                }

                $items["{$block}.{$item}"] = true;
            }
        }

        $items = array_keys($items);
        sort($items);

        $calls = [];

        if (preg_match_all(self::CALL_PATTERN, $source, $matches) > 0) {
            foreach ($matches[0] as $call) {
                $calls[strtolower($call)] = true;
            }
        }

        $calls = array_keys($calls);
        sort($calls);

        return [
            'tablas' => $tables,
            'escribe_tabla' => $writes,
            'mensajes' => $messages,
            'items' => $items,
            'llamadas' => $calls,
        ];
    }

    /**
     * Catalogo completo (estructura equivalente a <form>.reglas.json del prototipo).
     */
    public function build(string $form, ScanOutcome $outcome): array
    {
        $all = [...array_values($outcome->triggers), ...array_values($outcome->programUnits)];

        $blocks = [];
        $boilerplate = [];
        $globalTables = [];

        foreach ($all as $record) {
            $class = $this->classify($record->source);
            $analysis = $this->analyzeSource($record->source);

            foreach ($analysis['tablas'] as $table) {
                $globalTables[$table] = true;
            }

            if (str_starts_with($class, 'boilerplate')) {
                $boilerplate[] = ['name' => $record->name, 'clase' => $class];

                continue;
            }

            $scope = TriggerKind::scope($record->name) ?? 'PANTALLA';
            $parts = explode('.', $scope, 2);
            $block = $parts[0];
            $item = $parts[1] ?? null;

            if (! isset($blocks[$block])) {
                $blocks[$block] = [
                    'tabla' => $this->blockTables[$block] ?? null,
                    'items' => [],
                    'triggers_bloque' => [],
                ];
            }

            $entry = [
                'nombre' => $record->name,
                'tipo' => TriggerKind::type($record->name) ?? 'SIN-TIPO',
                'modificado' => $record->modified,
                'tablas' => $analysis['tablas'],
                'escribe' => $analysis['escribe_tabla'],
                'mensajes' => $analysis['mensajes'],
                'llamadas' => $analysis['llamadas'],
                'items_afectados' => $analysis['items'],
                'codigo' => $record->source,
            ];

            if ($item !== null) {
                $blocks[$block]['items'][$item][] = $entry;
            } else {
                $blocks[$block]['triggers_bloque'][] = $entry;
            }
        }

        $globalTables = array_keys($globalTables);
        sort($globalTables);

        return [
            'form' => $form,
            'bloques' => $blocks,
            'boilerplate' => $boilerplate,
            'tablas_globales' => $globalTables,
        ];
    }

    /**
     * Tabla candidata de un bloque: override explicito o inferencia B_X -> X.
     */
    public function tableForBlock(string $block): ?string
    {
        if (isset($this->blockTables[$block])) {
            return $this->blockTables[$block];
        }

        return str_starts_with($block, 'B_') ? substr($block, 2) : null;
    }

    public function boilerplateCount(array $reglas): int
    {
        return count($reglas['boilerplate']);
    }

    public function logicCount(array $reglas): int
    {
        $count = 0;

        foreach ($reglas['bloques'] as $block) {
            $count += count($block['triggers_bloque']);

            foreach ($block['items'] as $entries) {
                $count += count($entries);
            }
        }

        return $count;
    }

    /**
     * Reporte legible (equivalente a <form>.reglas.md del prototipo).
     */
    public function markdown(string $form, array $reglas): string
    {
        $md = [];

        $md[] = '# Catalogo de reglas - '.strtoupper($form)."\n";
        $md[] = "## Tablas referenciadas (todas)\n";
        $md[] = implode(', ', array_map(static fn (string $table): string => "`{$table}`", $reglas['tablas_globales']))."\n";

        foreach ($reglas['bloques'] as $block => $blk) {
            $md[] = "\n## Bloque `{$block}`"
                .($blk['tabla'] ? "  (tabla: `{$blk['tabla']}`)" : '')."\n";

            if ($blk['triggers_bloque'] !== []) {
                $md[] = "### Triggers del bloque\n";

                foreach ($blk['triggers_bloque'] as $entry) {
                    $md[] = "- **{$entry['nombre']}** ({$entry['tipo']}, mod {$entry['modificado']})";

                    if ($entry['tablas'] !== []) {
                        $md[] = '  - tablas: '.implode(', ', $entry['tablas']);
                    }

                    if ($entry['escribe'] !== []) {
                        $md[] = '  - escribe en: '.implode(', ', $entry['escribe']);
                    }

                    if ($entry['mensajes'] !== []) {
                        $md[] = '  - mensajes: '.implode(' | ', $entry['mensajes']);
                    }

                    if ($entry['llamadas'] !== []) {
                        $md[] = '  - llama a: '.implode(', ', $entry['llamadas']);
                    }
                }

                $md[] = '';
            }

            foreach ($blk['items'] as $item => $entries) {
                $md[] = "### Item `{$block}.{$item}`\n";

                foreach ($entries as $entry) {
                    $md[] = "- **{$entry['tipo']}** (mod {$entry['modificado']})";

                    if ($entry['tablas'] !== []) {
                        $md[] = '  - tablas: '.implode(', ', $entry['tablas']);
                    }

                    if ($entry['escribe'] !== []) {
                        $md[] = '  - escribe en: '.implode(', ', $entry['escribe']);
                    }

                    if ($entry['mensajes'] !== []) {
                        $md[] = '  - mensajes: '.implode(' | ', $entry['mensajes']);
                    }

                    if ($entry['llamadas'] !== []) {
                        $md[] = '  - llama a: '.implode(', ', $entry['llamadas']);
                    }
                }

                $md[] = '';
            }
        }

        $md[] = "\n## Triggers boilerplate (omitidos del detalle)\n";

        foreach ($reglas['boilerplate'] as $entry) {
            $md[] = "- {$entry['name']}  [{$entry['clase']}]";
        }

        return implode("\n", $md);
    }
}
