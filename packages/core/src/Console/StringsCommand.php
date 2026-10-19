<?php

declare(strict_types=1);

namespace Fmb2Laravel\Console;

use Fmb2Laravel\Catalog\CatalogExporter;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Fmb\Text;
use RuntimeException;

final class StringsCommand
{
    /**
     * @param  array<int, string>  $args
     */
    public function run(array $args): int
    {
        $out = Cli::option($args, 'out', './catalog');
        $min = (int) Cli::option($args, 'min', '4');
        $file = Cli::positional($args)[0] ?? null;

        if ($file === null) {
            IO::err('uso: fmb2laravel strings <archivo.fmb> [--out=DIR] [--min=4]');

            return 1;
        }

        $fmb = FmbFile::open($file);
        $pool = Text::pool($fmb->data, max(1, $min));

        $dir = rtrim((string) $out, '/\\');

        if ($dir === '') {
            $dir = '.';
        }

        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio: {$dir}");
        }

        $target = $dir.DIRECTORY_SEPARATOR.$fmb->stem().'.strings.txt';
        file_put_contents($target, CatalogExporter::stringsTxt($pool));

        IO::out(sprintf('%d cadenas (min %d) -> %s', count($pool), max(1, $min), $target));

        return 0;
    }
}
