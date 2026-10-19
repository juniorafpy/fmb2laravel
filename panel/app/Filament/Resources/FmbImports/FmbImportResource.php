<?php

namespace App\Filament\Resources\FmbImports;

use App\Filament\Resources\FmbImports\Pages\CreateFmbImport;
use App\Filament\Resources\FmbImports\Pages\ListFmbImports;
use App\Filament\Resources\FmbImports\Pages\ViewFmbImport;
use App\Filament\Resources\FmbImports\Schemas\FmbImportForm;
use App\Filament\Resources\FmbImports\Tables\FmbImportsTable;
use App\Models\FmbImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Recurso "Importacion FMB": sube modulos Oracle Forms (.fmb) y ejecuta la
 * extraccion de fmb2laravel (triggers, reglas de negocio, tablas, mensajes).
 */
class FmbImportResource extends Resource
{
    protected static ?string $model = FmbImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Importaciones';

    protected static ?string $modelLabel = 'importacion';

    protected static ?string $pluralModelLabel = 'importaciones';

    protected static ?string $recordTitleAttribute = 'archivo_original';

    public static function form(Schema $schema): Schema
    {
        return FmbImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FmbImportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFmbImports::route('/'),
            'create' => CreateFmbImport::route('/create'),
            'view' => ViewFmbImport::route('/{record}'),
        ];
    }
}
