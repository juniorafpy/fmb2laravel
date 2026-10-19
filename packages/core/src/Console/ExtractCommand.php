<?php

declare(strict_types=1);

namespace Fmb2Laravel\Console;

use Fmb2Laravel\Catalog\CatalogExporter;
use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecordScanner;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Fmb\Text;
use Fmb2Laravel\Ir\IrBuilder;
use RuntimeException;

final class ExtractCommand
{
    /**
     * @param  array<int, string>  $args
     */
    public function run(array $args): int
    {
        $out = Cli::option($args, 'out', './catalog');
        $configPath = Cli::option($args, 'config');
        $module = Cli::option($args, 'module');
        $withStrings = Cli::flag($args, 'strings');

        $file = Cli::positional($args, ['strings'])[0] ?? null;

        if ($file === null) {
            IO::err('uso: fmb2laravel extract <archivo.fmb> [--out=DIR] [--module=NAME] [--config=FILE] [--strings]');

            return 1;
        }

        $config = $this->readConfig($configPath);

        $fmb = FmbFile::open($file);
        $stem = $fmb->stem();
        $moduleName = $module ?? $config['module'] ?? $stem;

        $outcome = (new CodeRecordScanner($fmb->data))->scan();

        $analyzer = new RuleAnalyzer($config['blockTables'] ?? []);
        $irBuilder = new IrBuilder($analyzer);

        $ir = $irBuilder->build($fmb, $outcome, $moduleName);
        $reglas = $analyzer->build($moduleName, $outcome);

        $dir = $this->ensureDirectory((string) $out);

        $files = [
            "{$stem}.ir.json" => $irBuilder->encode($ir),
            "{$stem}.triggers.sql" => CatalogExporter::triggersSql($outcome->triggers),
            "{$stem}.triggers.json" => CatalogExporter::triggersJson($outcome->triggers),
            "{$stem}.program_units.json" => CatalogExporter::programUnitsJson($outcome->programUnits),
            "{$stem}.conflicts.json" => CatalogExporter::conflictsJson($outcome->conflicts),
            "{$stem}.reglas.json" => json_encode(
                $reglas,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )."\n",
            "{$stem}.reglas.md" => $analyzer->markdown($moduleName, $reglas),
        ];

        if ($withStrings) {
            $files["{$stem}.strings.txt"] = CatalogExporter::stringsTxt(Text::pool($fmb->data));
        }

        foreach ($files as $name => $content) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.$name, $content);
        }

        IO::out('fmb2laravel extract — '.$fmb->path.' -> '.$dir);
        IO::out();
        IO::out(sprintf(
            '  %d triggers, %d program units, %d conflicto(s)',
            count($outcome->triggers),
            count($outcome->programUnits),
            count($outcome->conflicts),
        ));
        IO::out(sprintf(
            '  Logica: %d triggers | Boilerplate omitido del detalle: %d',
            $analyzer->logicCount($reglas),
            $analyzer->boilerplateCount($reglas),
        ));
        IO::out();

        foreach (array_keys($files) as $name) {
            IO::out('  '.$dir.DIRECTORY_SEPARATOR.$name);
        }

        IO::out();
        IO::out('Siguiente paso: revisar '.$stem.'.reglas.md y '.$stem.'.conflicts.json.');

        return 0;
    }

    /**
     * @return array{module?: string, blockTables?: array<string, string>}
     */
    private function readConfig(?string $path): array
    {
        if ($path === null) {
            return [];
        }

        if (! is_file($path)) {
            throw new RuntimeException("No existe el archivo de configuracion: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("El archivo de configuracion no es JSON valido: {$path}");
        }

        return [
            'module' => isset($decoded['module']) ? (string) $decoded['module'] : null,
            'blockTables' => isset($decoded['blockTables']) && is_array($decoded['blockTables'])
                ? array_map(strval(...), $decoded['blockTables'])
                : [],
        ];
    }

    private function ensureDirectory(string $dir): string
    {
        $dir = rtrim($dir, '/\\');

        if ($dir === '') {
            $dir = '.';
        }

        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio: {$dir}");
        }

        return $dir;
    }
}
