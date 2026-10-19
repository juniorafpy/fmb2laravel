<?php

namespace App\Filament\Resources\FmbImports\Pages;

use App\Filament\Resources\FmbImports\FmbImportResource;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewFmbImport extends ViewRecord
{
    protected static string $resource = FmbImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Importacion: '.$this->getRecord()->archivo_original;
    }

    public function infolist(Schema $schema): Schema
    {
        $importacion = $this->getRecord();
        $resumen = $importacion->resumen ?? [];
        $procesada = $importacion->estado === 'ok';

        return $schema
            ->columns(1)
            ->components([
                Section::make('Archivo')
                    ->icon('heroicon-o-document')
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('archivo_original')
                            ->label('Archivo'),

                        TextEntry::make('modulo')
                            ->label('Modulo'),

                        TextEntry::make('estado')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'ok' => 'success',
                                'error' => 'danger',
                                default => 'warning',
                            }),

                        TextEntry::make('created_at')
                            ->label('Importado')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('tamano')
                            ->label('Tamano')
                            ->formatStateUsing(fn (?int $state): string => $state === null
                                ? '—'
                                : number_format($state / 1024, 1, ',', '.').' KB'),

                        TextEntry::make('sha256')
                            ->label('SHA-256')
                            ->placeholder('—'),

                        TextEntry::make('error')
                            ->label('Error')
                            ->color('danger')
                            ->visible($importacion->estado === 'error')
                            ->columnSpanFull(),
                    ]),

                Section::make('Extraccion')
                    ->icon('heroicon-o-sparkles')
                    ->visible($procesada)
                    ->columns(['default' => 2, 'md' => 3, 'lg' => 4])
                    ->schema([
                        TextEntry::make('triggers_count')
                            ->label('Triggers')
                            ->state($resumen['triggers'] ?? null)
                            ->numeric(),

                        TextEntry::make('program_units_count')
                            ->label('Program units')
                            ->state($resumen['program_units'] ?? null)
                            ->numeric(),

                        TextEntry::make('conflictos_count')
                            ->label('Conflictos')
                            ->state($resumen['conflictos'] ?? null)
                            ->numeric(),

                        TextEntry::make('bloques_count')
                            ->label('Bloques')
                            ->state($resumen['bloques'] ?? null)
                            ->numeric(),

                        TextEntry::make('items_count')
                            ->label('Items')
                            ->state($resumen['items'] ?? null)
                            ->numeric(),

                        TextEntry::make('logica_count')
                            ->label('Con logica')
                            ->state($resumen['logica'] ?? null)
                            ->numeric(),

                        TextEntry::make('boilerplate_count')
                            ->label('Boilerplate')
                            ->state($resumen['boilerplate'] ?? null)
                            ->numeric(),

                        TextEntry::make('mensajes_count')
                            ->label('Mensajes')
                            ->state($resumen['mensajes'] ?? null)
                            ->numeric(),

                        TextEntry::make('tablas_referenciadas')
                            ->label('Tablas referenciadas')
                            ->state($resumen['tablas'] ?? [])
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('tablas_escritura')
                            ->label('Tablas escritas')
                            ->state($resumen['tablas_escritura'] ?? [])
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Archivos generados')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible($procesada)
                    ->schema([
                        TextEntry::make('archivos')
                            ->hiddenLabel()
                            ->state($importacion->archivos ?? [])
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('—')
                            ->url(fn (string $state): string => route('filament.admin.fmb-import.download', [
                                'importacion' => $importacion,
                                'archivo' => $state,
                            ])),
                    ]),

                Section::make('Advertencias de la lectura heuristica')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible($procesada)
                    ->schema([
                        TextEntry::make('advertencias')
                            ->hiddenLabel()
                            ->state($resumen['advertencias'] ?? [])
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
