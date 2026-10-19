<?php

namespace App\Support;

use App\Models\FmbImport;
use Fmb2Laravel\Catalog\CatalogExporter;
use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecordScanner;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Ir\IrBuilder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Extraccion fmb2laravel de un .fmb importado: genera el catalogo (IR,
 * triggers, reglas, conflictos) y lo guarda en el disco local.
 *
 * Regla de oro del proyecto: nunca fallar en silencio. Si el modulo no se
 * puede leer, la importacion queda en estado "error" con el detalle y el
 * catalogo conserva lo que el lector pudo recuperar.
 */
class ImportadorFmb
{
    /**
     * Procesa la importacion y persiste estado, resumen y archivos generados.
     *
     * @throws RuntimeException si falta el archivo subido.
     */
    public static function procesar(FmbImport $importacion): void
    {
        $disco = Storage::disk('local');

        if (! $disco->exists($importacion->ruta_archivo)) {
            throw new RuntimeException('No se encontro el archivo subido: '.$importacion->ruta_archivo);
        }

        $fmb = FmbFile::open($disco->path($importacion->ruta_archivo));
        $outcome = (new CodeRecordScanner($fmb->data))->scan();

        $analizador = new RuleAnalyzer;
        $irBuilder = new IrBuilder($analizador);

        $modulo = filled($importacion->modulo) ? $importacion->modulo : $fmb->stem();

        $ir = $irBuilder->build($fmb, $outcome, $modulo);
        $reglas = $analizador->build($modulo, $outcome);

        $carpeta = $importacion->carpetaSalida();
        $disco->makeDirectory($carpeta);

        $archivos = [
            "{$modulo}.ir.json" => $irBuilder->encode($ir),
            "{$modulo}.triggers.sql" => CatalogExporter::triggersSql($outcome->triggers),
            "{$modulo}.triggers.json" => CatalogExporter::triggersJson($outcome->triggers),
            "{$modulo}.program_units.json" => CatalogExporter::programUnitsJson($outcome->programUnits),
            "{$modulo}.conflicts.json" => CatalogExporter::conflictsJson($outcome->conflicts),
            "{$modulo}.reglas.json" => json_encode(
                $reglas,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )."\n",
            "{$modulo}.reglas.md" => $analizador->markdown($modulo, $reglas),
        ];

        foreach ($archivos as $nombre => $contenido) {
            $disco->put($carpeta.'/'.$nombre, $contenido);
        }

        $zip = self::crearZip($disco->path($carpeta), $modulo, array_keys($archivos));

        $importacion->forceFill([
            'sha256' => $fmb->sha256(),
            'tamano' => $fmb->size(),
            'estado' => 'ok',
            'error' => null,
            'resumen' => [
                'records' => $ir['stats']['records'],
                'triggers' => $ir['stats']['triggers'],
                'program_units' => $ir['stats']['programUnits'],
                'conflictos' => $ir['stats']['conflicts'],
                'bloques' => $ir['stats']['blocks'],
                'items' => $ir['stats']['items'],
                'logica' => $analizador->logicCount($reglas),
                'boilerplate' => $analizador->boilerplateCount($reglas),
                'tablas' => $ir['tables']['referenced'],
                'tablas_escritura' => $ir['tables']['written'],
                'mensajes' => count($ir['messages']),
                'advertencias' => array_map(
                    static fn (array $warning): string => "[{$warning['code']}] {$warning['message']}",
                    $ir['warnings'],
                ),
            ],
            'archivos' => [
                ...array_keys($archivos),
                ...($zip !== null ? [$zip] : []),
            ],
        ])->save();
    }

    /**
     * Empaqueta el catalogo en un ZIP si la extension zip esta disponible.
     *
     * @param  array<int, string>  $nombres
     * @return string|null nombre del zip generado, o null si no se pudo crear
     */
    private static function crearZip(string $carpeta, string $modulo, array $nombres): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $nombre = "{$modulo}-catalogo.zip";
        $zip = new ZipArchive;

        if ($zip->open($carpeta.DIRECTORY_SEPARATOR.$nombre, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($nombres as $archivo) {
            $zip->addFile($carpeta.DIRECTORY_SEPARATOR.$archivo, $archivo);
        }

        return $zip->close() ? $nombre : null;
    }
}
