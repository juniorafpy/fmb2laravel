<?php

namespace App\Filament\Resources\FmbImports\Pages;

use App\Filament\Resources\FmbImports\FmbImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFmbImports extends ListRecords
{
    protected static string $resource = FmbImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Importar formulario'),
        ];
    }
}
