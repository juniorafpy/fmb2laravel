<?php

declare(strict_types=1);

namespace Fmb2Laravel\Console;

/**
 * Parser minimo de argumentos (sin dependencias).
 */
final class Cli
{
    /**
     * @param  array<int, string>  $args
     */
    public static function option(array $args, string $name, ?string $default = null): ?string
    {
        foreach ($args as $index => $arg) {
            if ($arg === "--{$name}" && isset($args[$index + 1])) {
                return $args[$index + 1];
            }

            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen($name) + 3);
            }
        }

        return $default;
    }

    /**
     * @param  array<int, string>  $args
     */
    public static function flag(array $args, string ...$names): bool
    {
        foreach ($names as $name) {
            if (in_array('--'.$name, $args, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve los argumentos que no son opciones ni valores de opciones.
     *
     * @param  array<int, string>  $args
     * @param  array<int, string>  $flags  nombres (sin "--") de opciones booleanas
     * @return array<int, string>
     */
    public static function positional(array $args, array $flags = []): array
    {
        $positional = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];

            if (str_starts_with($arg, '--')) {
                $name = substr($arg, 2);

                // "--clave=valor" no consume el siguiente argumento.
                if (str_contains($name, '=') || in_array($name, $flags, true)) {
                    continue;
                }

                $i++;

                continue;
            }

            if (str_starts_with($arg, '-')) {
                continue;
            }

            $positional[] = $arg;
        }

        return $positional;
    }
}
