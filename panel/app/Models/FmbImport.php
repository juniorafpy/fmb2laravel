<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Importacion de un modulo Oracle Forms (.fmb) via fmb2laravel.
 *
 * Los archivos generados (IR, triggers, reglas de negocio...) viven en el
 * disco local, en la carpeta devuelta por carpetaSalida().
 */
class FmbImport extends Model
{
    protected $table = 'fmb_imports';

    protected $guarded = [];

    protected $casts = [
        'resumen' => 'array',
        'archivos' => 'array',
        'tamano' => 'integer',
    ];

    /** Carpeta (disco local) donde viven los archivos generados. */
    public function carpetaSalida(): string
    {
        return 'fmb-imports/'.$this->getKey();
    }
}
