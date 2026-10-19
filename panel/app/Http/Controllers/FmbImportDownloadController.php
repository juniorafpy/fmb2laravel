<?php

namespace App\Http\Controllers;

use App\Models\FmbImport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga de un archivo generado por una importacion FMB.
 *
 * La ruta solo permite archivos que la importacion registro en su columna
 * `archivos` (nombres simples), y el archivo se sirve desde el disco local
 * privado (nunca desde public/).
 */
class FmbImportDownloadController extends Controller
{
    public function __invoke(FmbImport $importacion, string $archivo): BinaryFileResponse
    {
        abort_unless(
            in_array($archivo, $importacion->archivos ?? [], strict: true),
            404,
        );

        $ruta = Storage::disk('local')->path($importacion->carpetaSalida().'/'.$archivo);

        abort_unless(is_file($ruta), 404);

        return response()->download($ruta, $archivo);
    }
}
