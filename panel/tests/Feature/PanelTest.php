<?php

namespace Tests\Feature;

use App\Filament\Resources\FmbImports\FmbImportResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel no tiene login: la raiz redirige a la lista de importaciones y no
 * existe pantalla de acceso.
 */
class PanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_la_raiz_abre_la_lista_de_importaciones(): void
    {
        $this->get('/')->assertRedirect(FmbImportResource::getUrl('index'));

        $this->get(FmbImportResource::getUrl('index'))->assertOk();
    }

    public function test_no_existe_pantalla_de_login(): void
    {
        $this->get('/login')->assertNotFound();
    }
}
