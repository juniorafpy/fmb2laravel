<?php

declare(strict_types=1);

namespace Fmb2Laravel\Console;

/**
 * Salida del CLI. Los tests inyectan streams en memoria en $stdout / $stderr.
 */
final class IO
{
    /** @var resource|null */
    public static $stdout = null;

    /** @var resource|null */
    public static $stderr = null;

    public static function out(string $text = ''): void
    {
        fwrite(self::$stdout ?? STDOUT, $text."\n");
    }

    public static function err(string $text = ''): void
    {
        fwrite(self::$stderr ?? STDERR, $text."\n");
    }
}
