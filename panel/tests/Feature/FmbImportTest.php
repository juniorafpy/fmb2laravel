<?php

namespace Tests\Feature;

use App\Filament\Resources\FmbImports\FmbImportResource;
use App\Filament\Resources\FmbImports\Pages\CreateFmbImport;
use App\Models\FmbImport;
use App\Support\ImportadorFmb;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Importacion de modulos .fmb desde el panel abierto: extraccion fmb2laravel,
 * resumen, archivos generados y descargas.
 */
class FmbImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Storage::fake('local');
    }

    private function contenidoDemo(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/demo.fmb'));
    }

    private function crearImportacionPendiente(): FmbImport
    {
        Storage::disk('local')->put('fmb-imports/tmp/demo.fmb', $this->contenidoDemo());

        return FmbImport::create([
            'archivo_original' => 'demo.fmb',
            'ruta_archivo' => 'fmb-imports/tmp/demo.fmb',
            'modulo' => 'demo',
            'estado' => 'pendiente',
        ]);
    }

    public function test_el_importador_extrae_el_fixture_demo(): void
    {
        $importacion = $this->crearImportacionPendiente();

        ImportadorFmb::procesar($importacion);

        $importacion->refresh();

        $this->assertSame('ok', $importacion->estado);
        $this->assertSame(7, $importacion->resumen['triggers']);
        $this->assertSame(1, $importacion->resumen['program_units']);
        $this->assertSame(1, $importacion->resumen['conflictos']);
        $this->assertSame(1534, $importacion->tamano);
        $this->assertSame(
            '7028e6e50de490da85b47d83c2d0dff8bb31dc473e68bd94dc26baaa7668c07a',
            $importacion->sha256,
        );

        foreach ([
            'demo.ir.json',
            'demo.triggers.sql',
            'demo.triggers.json',
            'demo.program_units.json',
            'demo.conflicts.json',
            'demo.reglas.json',
            'demo.reglas.md',
            'demo-catalogo.zip',
        ] as $archivo) {
            Storage::disk('local')->assertExists("fmb-imports/{$importacion->id}/{$archivo}");
        }

        $this->assertContains('demo.reglas.md', $importacion->archivos);
        $this->assertContains('demo-catalogo.zip', $importacion->archivos);
        $this->assertNotEmpty($importacion->resumen['advertencias']);
    }

    public function test_el_importador_falla_si_no_existe_el_archivo(): void
    {
        $importacion = FmbImport::create([
            'archivo_original' => 'fantasma.fmb',
            'ruta_archivo' => 'fmb-imports/tmp/fantasma.fmb',
            'modulo' => 'fantasma',
            'estado' => 'pendiente',
        ]);

        $this->expectException(RuntimeException::class);

        ImportadorFmb::procesar($importacion);
    }

    public function test_la_pagina_de_importacion_se_renderiza(): void
    {
        $this->get(FmbImportResource::getUrl('create'))->assertOk();
    }

    public function test_se_puede_importar_un_fmb_desde_el_panel(): void
    {
        Livewire::test(CreateFmbImport::class)
            ->fillForm([
                'archivo' => [UploadedFile::fake()->createWithContent('demo.fmb', $this->contenidoDemo())],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $importacion = FmbImport::query()->firstOrFail();

        $this->assertSame('demo.fmb', $importacion->archivo_original);
        $this->assertSame('demo', $importacion->modulo);
        $this->assertSame('ok', $importacion->estado);
        $this->assertSame(7, $importacion->resumen['triggers']);

        Storage::disk('local')->assertExists("fmb-imports/{$importacion->id}/demo.reglas.md");
    }

    public function test_la_pagina_de_detalle_muestra_el_resumen_y_los_enlaces(): void
    {
        $importacion = $this->crearImportacionPendiente();

        ImportadorFmb::procesar($importacion);

        $html = $this->get(FmbImportResource::getUrl('view', ['record' => $importacion]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('demo.reglas.md', $html);
        $this->assertStringContainsString('/descarga', $html);
        $this->assertStringContainsString('Triggers', $html);
        $this->assertStringContainsString('HEURISTIC-LIMITS', $html);
    }

    public function test_los_archivos_generados_se_pueden_descargar(): void
    {
        $importacion = $this->crearImportacionPendiente();

        ImportadorFmb::procesar($importacion);

        $this->get(route('filament.admin.fmb-import.download', [
            'importacion' => $importacion,
            'archivo' => 'demo.reglas.md',
        ]))
            ->assertOk()
            ->assertDownload('demo.reglas.md');

        $this->get(route('filament.admin.fmb-import.download', [
            'importacion' => $importacion,
            'archivo' => 'no-registrado.txt',
        ]))
            ->assertNotFound();
    }
}
