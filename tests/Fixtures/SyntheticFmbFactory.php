<?php

declare(strict_types=1);

namespace Fmb2Laravel\Tests\Fixtures;

/**
 * Construye .FMB sinteticos con el mismo empaquetado que produce
 * Oracle Forms 6i, para tests hermeticos (sin binarios reales en CI).
 *
 *     [len]"P<id>_<DD>_<MMM>_<YYYY><HH>_<MM>_<SS>"
 *     [len]"BEGIN\n" [len]chunk ... [len]"\nEND;"
 *     [len]"TIPO (BLOQUE.ITEM)"
 */
final class SyntheticFmbFactory
{
    /** Relleno no textual entre registros (evita falsos positivos del lector). */
    public const PAD = "\xFF\xFE\xFD\xFC";

    public static function pid(int $id, string $dd, string $mon, string $yyyy, string $hh, string $mi, string $ss): string
    {
        return sprintf('P%d_%s_%s_%s%s_%s_%s', $id, $dd, $mon, $yyyy, $hh, $mi, $ss);
    }

    public static function chunk(string $text): string
    {
        return pack('N', strlen($text)).$text;
    }

    /**
     * Registro moderno (con encabezado P<id>).
     *
     * @param  array<int, string>  $bodyChunks  trozos del cuerpo entre "BEGIN" y "END;"
     */
    public static function record(string $name, array $bodyChunks, string $pid): string
    {
        return self::PAD
            .self::chunk($pid)
            .self::chunk("BEGIN\n")
            .implode('', array_map(self::chunk(...), $bodyChunks))
            .self::chunk("\nEND;")
            .self::chunk($name)
            .self::PAD;
    }

    /**
     * Registro antiguo (sin P<id>): el cuerpo arranca en el ancla "BEGIN\n".
     *
     * @param  array<int, string>  $bodyChunks
     */
    public static function legacyRecord(string $name, array $bodyChunks): string
    {
        return self::PAD
            .self::chunk("BEGIN\n")
            .implode('', array_map(self::chunk(...), $bodyChunks))
            .self::chunk("\nEND;")
            .self::chunk($name)
            .self::PAD;
    }

    public static function file(string ...$records): string
    {
        return self::PAD.implode('', $records).self::PAD;
    }
}
