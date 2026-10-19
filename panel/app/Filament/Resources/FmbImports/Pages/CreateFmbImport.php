<?php

namespace App\Filament\Resources\FmbImports\Pages;

use App\Filament\Resources\FmbImports\FmbImportResource;
use App\Models\FmbImport;
use App\Support\ImportadorFmb;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateFmbImport extends CreateRecord
{
    protected static string $resource = FmbImportResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Normaliza los datos del formulario: guarda la ruta del archivo subido,
     * valida la extension .fmb y completa el modulo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $ruta = $data['archivo'] ?? null;
        $ruta = is_array($ruta) ? collect($ruta)->filter()->first() : $ruta;

        if (! is_string($ruta) || $ruta === '') {
            throw ValidationException::withMessages([
                'data.archivo' => 'Debes subir un archivo .fmb.',
            ]);
        }

        $nombreOriginal = $data['archivo_nombre'] ?? null;
        $nombreOriginal = is_array($nombreOriginal) ? reset($nombreOriginal) : $nombreOriginal;
        $nombreOriginal = is_string($nombreOriginal) && $nombreOriginal !== ''
            ? $nombreOriginal
            : basename($ruta);

        if (strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION)) !== 'fmb') {
            throw ValidationException::withMessages([
                'data.archivo' => 'El archivo debe tener extension .fmb (recibido: '.$nombreOriginal.').',
            ]);
        }

        unset($data['archivo'], $data['archivo_nombre']);

        return [
            ...$data,
            'archivo_original' => $nombreOriginal,
            'modulo' => pathinfo($nombreOriginal, PATHINFO_FILENAME),
            'ruta_archivo' => $ruta,
            'estado' => 'pendiente',
        ];
    }

    /**
     * Ejecuta la extraccion fmb2laravel una vez creada la importacion.
     */
    protected function afterCreate(): void
    {
        /** @var FmbImport $importacion */
        $importacion = $this->getRecord();

        try {
            ImportadorFmb::procesar($importacion);

            Notification::make()
                ->success()
                ->title('Extraccion completada')
                ->body(sprintf(
                    '%s: %d triggers, %d program units, %d conflicto(s), %d con logica.',
                    $importacion->archivo_original,
                    $importacion->resumen['triggers'] ?? 0,
                    $importacion->resumen['program_units'] ?? 0,
                    $importacion->resumen['conflictos'] ?? 0,
                    $importacion->resumen['logica'] ?? 0,
                ))
                ->send();
        } catch (Throwable $excepcion) {
            $importacion->forceFill([
                'estado' => 'error',
                'error' => $excepcion->getMessage(),
            ])->save();

            Notification::make()
                ->danger()
                ->title('No se pudo procesar el archivo')
                ->body($excepcion->getMessage())
                ->persistent()
                ->send();
        }
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Importar y procesar');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }
}
