<?php

declare(strict_types=1);

namespace Fmb2Laravel\Catalog;

use Fmb2Laravel\Fmb\CodeRecord;
use Fmb2Laravel\Fmb\Text;
use Fmb2Laravel\Fmb\TriggerKind;

/**
 * Serializa el resultado de la extraccion a los formatos del catalogo:
 * JSON estructurado, SQL legible y volcado del pool de cadenas.
 */
final class CatalogExporter
{
    /**
     * @param  array<string, CodeRecord>  $triggers
     */
    public static function triggersJson(array $triggers): string
    {
        return self::encode(self::entries($triggers, withKind: true));
    }

    /**
     * @param  array<string, CodeRecord>  $programUnits
     */
    public static function programUnitsJson(array $programUnits): string
    {
        return self::encode(self::entries($programUnits, withKind: false));
    }

    /**
     * @param  array<int, array<string, mixed>>  $conflicts
     */
    public static function conflictsJson(array $conflicts): string
    {
        return self::encode(array_values($conflicts));
    }

    /**
     * @param  array<string, CodeRecord>  $triggers
     */
    public static function triggersSql(array $triggers): string
    {
        $sql = '';

        foreach ($triggers as $record) {
            $sql .= "-- ============================================================\n";
            $sql .= "-- {$record->name}   (mod {$record->modified}, {$record->versions} version(es))\n";
            $sql .= "-- ============================================================\n";
            $sql .= $record->source."\n\n";
        }

        return $sql;
    }

    /**
     * @param  array<int, array{offset: int, raw: string}>  $pool
     */
    public static function stringsTxt(array $pool): string
    {
        $output = '';

        foreach ($pool as $entry) {
            $output .= sprintf('%08X', $entry['offset'])."\t".Text::decode($entry['raw'])."\n";
        }

        return $output;
    }

    /**
     * @param  array<string, CodeRecord>  $records
     * @return array<int, array<string, mixed>>
     */
    private static function entries(array $records, bool $withKind): array
    {
        $entries = [];

        foreach ($records as $record) {
            $entry = [
                'name' => $record->name,
                'modified' => $record->modified,
                'pid' => $record->pid,
                'versions' => $record->versions,
                'source' => $record->source,
            ];

            if ($withKind) {
                $entry['type'] = TriggerKind::type($record->name);
                $entry['scope'] = TriggerKind::scope($record->name);
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    private static function encode(mixed $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        )."\n";
    }
}
