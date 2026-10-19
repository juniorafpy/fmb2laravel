<?php

namespace App\Filament\Resources\FmbImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Formulario de importacion: sube el .fmb y lo procesa con fmb2laravel.
 */
class FmbImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Modulo Oracle Forms (.fmb)')
                    ->description('1) Sube el archivo. 2) Presiona "Importar y procesar" (abajo del formulario). El lector heuristico de fmb2laravel extrae los triggers, las reglas de negocio, las tablas y los mensajes — sin instalar Oracle — y al terminar te muestra el resumen con los archivos descargables.')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('archivo')
                            ->label('Archivo .fmb')
                            ->disk('local')
                            ->directory('fmb-imports/tmp')
                            ->storeFileNamesIn('archivo_nombre')
                            ->required()
                            ->maxSize(20480)
                            ->helperText('Maximo 20 MB. El resumen y los archivos generados aparecen en la pantalla siguiente.'),
                    ]),
            ]);
    }
}
