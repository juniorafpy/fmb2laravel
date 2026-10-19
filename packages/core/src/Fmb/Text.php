<?php

declare(strict_types=1);

namespace Fmb2Laravel\Fmb;

/**
 * Utilidades de texto sobre bytes crudos del .FMB.
 *
 * Port de fmb_strings.py / is_text() del prototipo Python:
 * el formato guarda texto en CP1252 (Latin-1 extendido).
 */
final class Text
{
    /**
     * True si el contenido es "texto" segun la heuristica del formato:
     * tab/newline/CR, ASCII imprimible o bytes extendidos CP1252.
     */
    public static function isDecodable(string $raw): bool
    {
        $length = strlen($raw);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($raw[$i]);

            if ($byte === 9 || $byte === 10 || $byte === 13) {
                continue;
            }

            if ($byte >= 32 && $byte <= 126) {
                continue;
            }

            if ($byte >= 160 && $byte <= 255) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * CP1252 -> UTF-8, equivalente a bytes.decode("cp1252", errors="replace").
     *
     * Los bytes indefinidos de CP1252 (0x81, 0x8D, 0x8F, 0x90, 0x9D) nunca
     * llegan aqui porque isDecodable() ya los descarta.
     */
    public static function decode(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        return mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    /**
     * Volcado del pool de cadenas legibles (port de extract_strings() de fmb_strings.py).
     *
     * @return array<int, array{offset: int, raw: string}>
     */
    public static function pool(string $data, int $minLength = 4): array
    {
        $results = [];
        $length = strlen($data);
        $start = 0;
        $current = '';

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($data[$i]);

            if (($byte >= 32 && $byte <= 126) || ($byte >= 160 && $byte <= 255)) {
                if ($current === '') {
                    $start = $i;
                }

                $current .= $data[$i];

                continue;
            }

            if (strlen($current) >= $minLength) {
                $results[] = ['offset' => $start, 'raw' => $current];
            }

            $current = '';
        }

        if (strlen($current) >= $minLength) {
            $results[] = ['offset' => $start, 'raw' => $current];
        }

        return $results;
    }
}
