<?php

declare(strict_types=1);

namespace Fmb2Laravel\Fmb;

/**
 * Interpreta el nombre de un objeto: "WHEN-VALIDATE-ITEM (B_ENTITY.ITEM)".
 *
 * Port de TRIGGER_RE / PID_RE del prototipo Python (fmb_parse.py).
 */
final class TriggerKind
{
    private const NAME_PATTERN = '/^((?:WHEN|PRE|POST|KEY|ON)-[A-Z0-9-]+)\s*\(([^)]*)\)$/';

    private const PID_PATTERN = '/^P(\d+)_(\d{2})_([A-Z]{3})_(\d{4})(\d{2})_(\d{2})_(\d{2})$/';

    private const MONTHS = [
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
        'JAN' => 1, 'APR' => 4, 'AUG' => 8, 'DEC' => 12,
    ];

    public static function isTrigger(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    public static function type(string $name): ?string
    {
        return preg_match(self::NAME_PATTERN, $name, $matches) === 1
            ? strtoupper($matches[1])
            : null;
    }

    public static function scope(string $name): ?string
    {
        return preg_match(self::NAME_PATTERN, $name, $matches) === 1
            ? strtoupper(trim($matches[2]))
            : null;
    }

    /**
     * Parsea "P32_14_SEP_200909_44_41".
     *
     * @return array{0: string, 1: string}|null [clave de orden, fecha legible]
     */
    public static function parsePid(string $text): ?array
    {
        if (preg_match(self::PID_PATTERN, $text, $matches) !== 1) {
            return null;
        }

        $pid = $matches[1];
        $dd = $matches[2];
        $mon = $matches[3];
        $yyyy = $matches[4];
        $hh = $matches[5];
        $mi = $matches[6];
        $ss = $matches[7];

        $month = self::MONTHS[$mon] ?? null;

        if ($month === null) {
            return null;
        }

        $sortKey = sprintf(
            '%04d%02d%02d%02d%02d%02d%07d',
            (int) $yyyy,
            $month,
            (int) $dd,
            (int) $hh,
            (int) $mi,
            (int) $ss,
            (int) $pid,
        );

        $human = sprintf('%s-%s-%s %s:%s:%s', $dd, $mon, $yyyy, $hh, $mi, $ss);

        return [$sortKey, $human];
    }
}
