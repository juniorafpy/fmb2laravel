<?php

namespace App\Providers\Filament;

use App\Http\Controllers\FmbImportDownloadController;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel del importador: subir modulos .fmb y ver la extraccion de fmb2laravel.
 *
 * - Sin login ni usuarios: el panel vive en la raiz y no exige autenticacion.
 * - Sin dashboard: Filament registra la raiz y redirige al primer item del
 *   menu, la lista de importaciones.
 * - Los archivos generados se descargan por una ruta propia con lista blanca
 *   de nombres (nunca desde public/).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->brandName('fmb2laravel')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->userMenu(false)
            ->routes(function (): void {
                Route::get('fmb-imports/{importacion}/{archivo}/descarga', FmbImportDownloadController::class)
                    ->name('fmb-import.download')
                    ->where('archivo', '[A-Za-z0-9._-]+');
            });
    }
}
