<?php

namespace App\Filament\Resources\FmbImports\Tables;

use App\Models\FmbImport;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FmbImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('archivo_original')
                    ->label('Archivo')
                    ->searchable(),

                TextColumn::make('modulo')
                    ->label('Modulo')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (FmbImport $record): string => match ($record->estado) {
                        'ok' => 'success',
                        'error' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('triggers')
                    ->label('Triggers')
                    ->state(fn (FmbImport $record): ?int => $record->resumen['triggers'] ?? null)
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('conflictos')
                    ->label('Conflictos')
                    ->state(fn (FmbImport $record): ?int => $record->resumen['conflictos'] ?? null)
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('tamano')
                    ->label('Tamano')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : number_format($state / 1024, 1, ',', '.').' KB')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Importado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
