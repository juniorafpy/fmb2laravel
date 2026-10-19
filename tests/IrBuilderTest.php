<?php

declare(strict_types=1);

namespace Fmb2Laravel\Tests;

use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecordScanner;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Ir\IrBuilder;
use Fmb2Laravel\Tests\Fixtures\SyntheticFmbFactory;
use PHPUnit\Framework\TestCase;

final class IrBuilderTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->tempFiles = [];

        parent::tearDown();
    }

    public function test_construye_ir_con_bloques_items_y_avisos(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'WHEN-VALIDATE-ITEM (B_TAREAS.COD_ESTADO)',
                ["SELECT COUNT(*) FROM tareas WHERE cod = 1;\n", "message('Debe indicar estado');\n"],
                SyntheticFmbFactory::pid(10, '01', 'ENE', '2024', '08', '00', '00'),
            ),
            SyntheticFmbFactory::record(
                'ON-CHECK-DELETE-MASTER (B_TAREAS)',
                ["DELETE FROM tarea_afectada WHERE cod = 1;\n"],
                SyntheticFmbFactory::pid(11, '02', 'ENE', '2024', '08', '00', '00'),
            ),
            SyntheticFmbFactory::record(
                'KEY-CLRFRM (PANTALLA)',
                ["clear_form;\n"],
                SyntheticFmbFactory::pid(12, '03', 'ENE', '2024', '08', '00', '00'),
            ),
        );

        $path = $this->tempFmb($data);
        $fmb = FmbFile::open($path);
        $outcome = (new CodeRecordScanner($fmb->data))->scan();

        $builder = new IrBuilder(new RuleAnalyzer);
        $ir = $builder->build($fmb, $outcome, 'demo');

        $this->assertSame('1.0', $ir['irVersion']);
        $this->assertSame('fmb2laravel', $ir['generator']['name']);
        $this->assertSame('demo', $ir['module']['name']);
        $this->assertSame(basename($path), $ir['source']['file']);
        $this->assertSame(strlen($data), $ir['source']['sizeBytes']);

        $this->assertSame(3, $ir['stats']['triggers']);
        $this->assertSame(0, $ir['stats']['programUnits']);
        $this->assertSame(0, $ir['stats']['conflicts']);
        $this->assertSame(1, $ir['stats']['blocks']);
        $this->assertSame(1, $ir['stats']['items']);

        $block = $ir['blocks'][0];

        $this->assertSame('B_TAREAS', $block['name']);
        $this->assertSame('TAREAS', $block['candidateTable']);
        $this->assertSame([1], $block['triggers']);
        $this->assertSame([2], $block['items'][0]['triggers']);
        $this->assertSame('COD_ESTADO', $block['items'][0]['name']);

        // Orden alfabetico de triggers: KEY-CLRFRM (0), ON-CHECK (1), WHEN-VALIDATE (2).
        $this->assertNull($ir['triggers'][0]['block']);
        $this->assertSame('PANTALLA', $ir['triggers'][0]['scope']);

        $entry = $ir['triggers'][2];

        $this->assertSame('WHEN-VALIDATE-ITEM', $entry['type']);
        $this->assertSame('B_TAREAS.COD_ESTADO', $entry['scope']);
        $this->assertSame('B_TAREAS', $entry['block']);
        $this->assertSame('COD_ESTADO', $entry['item']);
        $this->assertSame('logica', $entry['classification']);
        $this->assertSame(['tareas'], $entry['readsTables']);
        $this->assertSame(['Debe indicar estado'], $entry['messages']);

        $this->assertSame(['tarea_afectada', 'tareas'], $ir['tables']['referenced']);
        $this->assertSame(['TAREA_AFECTADA'], $ir['tables']['written']);
        $this->assertSame(['Debe indicar estado'], $ir['messages']);

        $codes = array_column($ir['warnings'], 'code');

        $this->assertContains('HEURISTIC-LIMITS', $codes);
        $this->assertContains('NO-FORMS-VERSION', $codes);
        $this->assertNotContains('VERSION-CONFLICT', $codes);

        $this->assertSame($ir, json_decode($builder->encode($ir), true));
    }

    public function test_avisa_cuando_hay_conflictos_de_version(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'KEY-EXIT (PANTALLA)',
                ["exit_form;\n"],
                SyntheticFmbFactory::pid(1, '01', 'ENE', '2024', '08', '00', '00'),
            ),
            SyntheticFmbFactory::record(
                'KEY-EXIT (PANTALLA)',
                ["exit_form(no_validate);\n"],
                SyntheticFmbFactory::pid(2, '02', 'ENE', '2024', '08', '00', '00'),
            ),
        );

        $path = $this->tempFmb($data);
        $fmb = FmbFile::open($path);
        $outcome = (new CodeRecordScanner($fmb->data))->scan();
        $ir = (new IrBuilder(new RuleAnalyzer))->build($fmb, $outcome, 'demo');

        $this->assertSame(1, $ir['stats']['conflicts']);
        $this->assertSame('VERSION-CONFLICT', $ir['warnings'][0]['code']);
        $this->assertSame(2, $ir['triggers'][0]['versions']);
    }

    private function tempFmb(string $contents): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fmb2laravel-'.uniqid().'.fmb';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
