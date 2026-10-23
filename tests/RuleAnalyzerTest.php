<?php

declare(strict_types=1);

namespace Fmb2Laravel\Tests;

use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecord;
use Fmb2Laravel\Fmb\ScanOutcome;
use PHPUnit\Framework\TestCase;

final class RuleAnalyzerTest extends TestCase
{
    public function test_clasifica_boilerplate_y_logica(): void
    {
        $analyzer = new RuleAnalyzer;

        $this->assertSame('logica', $analyzer->classify("BEGIN\n  null;\nEND;"));

        $this->assertSame(
            'boilerplate_lov',
            $analyzer->classify("BEGIN\n  do_key('list_values');\n  v := get_item_property('x', y);\nEND;"),
        );

        $this->assertSame(
            'boilerplate_nav',
            $analyzer->classify("BEGIN\n  if LOV_NAME is not null then\n    b_entity.lov := 1;\n  end if;\nEND;"),
        );
    }

    public function test_analiza_tablas_mensajes_items_y_llamadas(): void
    {
        $source = <<<'PLSQL'
        BEGIN
          SELECT t.cod_record, e.nombre
            INTO v_cod, v_nombre
            FROM entities t, empleado e
           WHERE t.cod = :b_entity.status;

          INSERT INTO bs.detail_table (a, b) VALUES (1, 2);
          UPDATE entities SET x = 1 WHERE cod = 2;
          DELETE FROM detail_table WHERE a = 1;

          message('Primer mensaje');
          alerta('Segundo mensaje');
          Mensaje('Tercer mensaje');

          :b_entity.status := '1';
          :variables.dias := 3;
          :global.x := 2;

          Permisos.Usuario(:x);
          inicializa_globales;
        END;
        PLSQL;

        $analyzer = new RuleAnalyzer;
        $result = $analyzer->analyzeSource($source);

        $this->assertSame(
            ['bs.detail_table', 'detail_table', 'entities', 'v_cod'],
            $result['tablas'],
        );
        $this->assertSame(
            ['BS.DETAIL_TABLE', 'DETAIL_TABLE', 'ENTITIES'],
            $result['escribe_tabla'],
        );
        $this->assertSame(
            ['Primer mensaje', 'Segundo mensaje', 'Tercer mensaje'],
            $result['mensajes'],
        );
        $this->assertSame(['B_ENTITY.STATUS'], $result['items']);
        $this->assertSame(
            ['alerta', 'inicializa_globales', 'mensaje', 'permisos.usuario'],
            $result['llamadas'],
        );
    }

    public function test_ignora_tablas_pseudo(): void
    {
        $analyzer = new RuleAnalyzer;
        $result = $analyzer->analyzeSource('SELECT 1 FROM dual; SELECT 2 FROM sys.dual;');

        $this->assertSame([], $result['tablas']);
    }

    public function test_build_agrupa_por_bloque_e_item(): void
    {
        $triggers = [
            'KEY-CLRFRM (PANTALLA)' => new CodeRecord(
                offset: 0,
                pid: 'P1_01_ENE_202408_00_00',
                modifiedSort: '202401010800000000001',
                modified: '01-ENE-2024 08:00:00',
                name: 'KEY-CLRFRM (PANTALLA)',
                source: "BEGIN\n  clear_form;\nEND;",
            ),
            'ON-CHECK-DELETE-MASTER (B_ENTITY)' => new CodeRecord(
                offset: 100,
                pid: 'P2_01_ENE_202408_00_01',
                modifiedSort: '202401010800010000002',
                modified: '01-ENE-2024 08:00:01',
                name: 'ON-CHECK-DELETE-MASTER (B_ENTITY)',
                source: "BEGIN\n  DELETE FROM detail_table WHERE cod = 1;\n  message('Operacion no permitida');\nEND;",
            ),
            'WHEN-VALIDATE-ITEM (B_ENTITY.STATUS)' => new CodeRecord(
                offset: 200,
                pid: 'P3_01_ENE_202408_00_02',
                modifiedSort: '202401010800020000003',
                modified: '01-ENE-2024 08:00:02',
                name: 'WHEN-VALIDATE-ITEM (B_ENTITY.STATUS)',
                source: "BEGIN\n  SELECT COUNT(*) FROM entities WHERE cod = 1;\n  :b_entity.fec_inicio := sysdate;\nEND;",
            ),
            'WHEN-LIST-CHANGED (B_DETAIL.COD_MODULO)' => new CodeRecord(
                offset: 300,
                pid: 'P4_01_ENE_202408_00_03',
                modifiedSort: '202401010800030000004',
                modified: '01-ENE-2024 08:00:03',
                name: 'WHEN-LIST-CHANGED (B_DETAIL.COD_MODULO)',
                source: "BEGIN\n  do_key('list_values');\n  v := get_item_property('x', y);\nEND;",
            ),
        ];

        $programUnits = [
            'PKG_ENTITY_UTIL' => new CodeRecord(
                offset: 400,
                pid: 'P5_01_ENE_202408_00_04',
                modifiedSort: '202401010800040000005',
                modified: '01-ENE-2024 08:00:04',
                name: 'PKG_ENTITY_UTIL',
                source: "BEGIN\n  inicializa_globales;\nEND;",
            ),
        ];

        $outcome = new ScanOutcome($triggers, $programUnits, [], 6);

        $analyzer = new RuleAnalyzer(['B_ENTITY' => 'ENTITY', 'B_DETAIL' => 'BS.DETAIL_TABLE']);
        $reglas = $analyzer->build('demo', $outcome);

        $this->assertSame('demo', $reglas['form']);
        $this->assertSame(['detail_table', 'entities'], $reglas['tablas_globales']);

        $this->assertSame(
            [
                ['name' => 'WHEN-LIST-CHANGED (B_DETAIL.COD_MODULO)', 'clase' => 'boilerplate_lov'],
            ],
            $reglas['boilerplate'],
        );

        $this->assertSame(['PANTALLA', 'B_ENTITY'], array_keys($reglas['bloques']));
        $this->assertSame('ENTITY', $reglas['bloques']['B_ENTITY']['tabla']);

        $this->assertCount(1, $reglas['bloques']['B_ENTITY']['triggers_bloque']);
        $this->assertSame(
            'ON-CHECK-DELETE-MASTER (B_ENTITY)',
            $reglas['bloques']['B_ENTITY']['triggers_bloque'][0]['nombre'],
        );
        $this->assertSame(
            ['Operacion no permitida'],
            $reglas['bloques']['B_ENTITY']['triggers_bloque'][0]['mensajes'],
        );

        $this->assertArrayHasKey('STATUS', $reglas['bloques']['B_ENTITY']['items']);
        $this->assertSame(
            'WHEN-VALIDATE-ITEM',
            $reglas['bloques']['B_ENTITY']['items']['STATUS'][0]['tipo'],
        );
        $this->assertSame(
            ['entities'],
            $reglas['bloques']['B_ENTITY']['items']['STATUS'][0]['tablas'],
        );
        $this->assertSame(
            ['B_ENTITY.FEC_INICIO'],
            $reglas['bloques']['B_ENTITY']['items']['STATUS'][0]['items_afectados'],
        );

        $this->assertArrayHasKey('PANTALLA', $reglas['bloques']);
        $this->assertCount(2, $reglas['bloques']['PANTALLA']['triggers_bloque']);
        $this->assertSame(
            'SIN-TIPO',
            $reglas['bloques']['PANTALLA']['triggers_bloque'][1]['tipo'],
        );

        $this->assertSame(4, $analyzer->logicCount($reglas));
        $this->assertSame(1, $analyzer->boilerplateCount($reglas));
    }

    public function test_markdown_incluye_secciones(): void
    {
        $triggers = [
            'WHEN-VALIDATE-ITEM (B_ENTITY.STATUS)' => new CodeRecord(
                offset: 0,
                pid: 'P1_01_ENE_202408_00_00',
                modifiedSort: '202401010800000000001',
                modified: '01-ENE-2024 08:00:00',
                name: 'WHEN-VALIDATE-ITEM (B_ENTITY.STATUS)',
                source: "BEGIN\n  message('Hola mundo');\nEND;",
            ),
        ];

        $analyzer = new RuleAnalyzer(['B_ENTITY' => 'ENTITY']);
        $reglas = $analyzer->build('demo', new ScanOutcome($triggers, [], [], 1));
        $md = $analyzer->markdown('demo', $reglas);

        $this->assertStringContainsString('# Catalogo de reglas - DEMO', $md);
        $this->assertStringContainsString('## Tablas referenciadas (todas)', $md);
        $this->assertStringContainsString('## Bloque `B_ENTITY`  (tabla: `ENTITY`)', $md);
        $this->assertStringContainsString('### Item `B_ENTITY.STATUS`', $md);
        $this->assertStringContainsString('- mensajes: Hola mundo', $md);
        $this->assertStringContainsString('## Triggers boilerplate (omitidos del detalle)', $md);
    }
}
