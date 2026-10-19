<?php

declare(strict_types=1);

namespace Fmb2Laravel\Tests;

use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecordScanner;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Ir\IrBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Verifica el fixture publico tests/Fixtures/demo.fmb (regenerable con
 * tests/Fixtures/generate-demo.php). Asi, cualquiera puede probar el
 * producto con un .fmb real sin depender de datos de una empresa.
 */
final class DemoFixtureTest extends TestCase
{
    private const DEMO = __DIR__.'/Fixtures/demo.fmb';

    public function test_el_demo_tiene_las_caracteristicas_esperadas(): void
    {
        $this->assertFileExists(
            self::DEMO,
            'Falta tests/Fixtures/demo.fmb: regenera con "php tests/Fixtures/generate-demo.php".'
        );

        $fmb = FmbFile::open(self::DEMO);
        $outcome = (new CodeRecordScanner($fmb->data))->scan();

        $this->assertCount(7, $outcome->triggers);
        $this->assertCount(1, $outcome->programUnits);
        $this->assertCount(1, $outcome->conflicts);
        $this->assertSame(9, $outcome->totalRecords);

        $this->assertArrayHasKey('PKG_DEMO_UTIL', $outcome->programUnits);

        $this->assertSame(
            'WHEN-BUTTON-PRESSED (B_DEMO.BTN_BORRAR)',
            $outcome->conflicts[0]['name'],
        );
        $this->assertSame(2, $outcome->triggers['WHEN-BUTTON-PRESSED (B_DEMO.BTN_BORRAR)']->versions);
        $this->assertStringContainsString(
            'No hay registro que borrar',
            $outcome->triggers['WHEN-BUTTON-PRESSED (B_DEMO.BTN_BORRAR)']->source,
        );

        $legacy = $outcome->triggers['ON-CHECK-DELETE-MASTER (B_DEMO)'];
        $this->assertNull($legacy->pid);
        $this->assertSame('(sin fecha)', $legacy->modified);

        $analyzer = new RuleAnalyzer;
        $ir = (new IrBuilder($analyzer))->build($fmb, $outcome, 'demo');

        $this->assertSame(7, $ir['stats']['triggers']);
        $this->assertSame(1, $ir['stats']['conflicts']);

        // El bloque B_DEMO se infiere como tabla candidata DEMO.
        $blocks = $ir['blocks'];
        $this->assertCount(1, $blocks);
        $this->assertSame('B_DEMO', $blocks[0]['name']);
        $this->assertSame('DEMO', $blocks[0]['candidateTable']);

        // Hay boilerplate detectado (el trigger LOV).
        $reglas = $analyzer->build('demo', $outcome);
        $this->assertSame(1, $analyzer->boilerplateCount($reglas));

        // El mensaje de negocio quedo indexado.
        $this->assertContains('Estado invalido', $ir['messages']);
    }
}
