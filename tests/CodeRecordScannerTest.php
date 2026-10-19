<?php

declare(strict_types=1);

namespace Fmb2Laravel\Tests;

use Fmb2Laravel\Fmb\CodeRecordScanner;
use Fmb2Laravel\Fmb\TriggerKind;
use Fmb2Laravel\Tests\Fixtures\SyntheticFmbFactory;
use PHPUnit\Framework\TestCase;

final class CodeRecordScannerTest extends TestCase
{
    public function test_parsea_un_trigger_completo(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'WHEN-VALIDATE-ITEM (B_TAREAS.COD_ESTADO)',
                ["if :b_tareas.cod_estado is null then\n", "  null;\n", 'end if;'],
                SyntheticFmbFactory::pid(19, '17', 'NOV', '2011', '07', '24', '32'),
            ),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertCount(1, $outcome->triggers);
        $this->assertCount(0, $outcome->programUnits);
        $this->assertSame([], $outcome->conflicts);
        $this->assertSame(1, $outcome->totalRecords);

        $trigger = $outcome->triggers['WHEN-VALIDATE-ITEM (B_TAREAS.COD_ESTADO)'];

        $this->assertSame('P19_17_NOV_201107_24_32', $trigger->pid);
        $this->assertSame('17-NOV-2011 07:24:32', $trigger->modified);
        $this->assertSame(1, $trigger->versions);
        $this->assertSame(
            "BEGIN\nif :b_tareas.cod_estado is null then\n  null;\nend if;\nEND;",
            $trigger->source,
        );
    }

    public function test_deduplica_versiones_y_conserva_la_mas_nueva(): void
    {
        $cuerpo = ["null;\n"];

        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'KEY-EXIT (PANTALLA)',
                $cuerpo,
                SyntheticFmbFactory::pid(1, '01', 'ENE', '2024', '08', '00', '00'),
            ),
            SyntheticFmbFactory::record(
                'KEY-EXIT (PANTALLA)',
                $cuerpo,
                SyntheticFmbFactory::pid(2, '15', 'JUN', '2025', '19', '30', '05'),
            ),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertSame(2, $outcome->totalRecords);
        $this->assertCount(1, $outcome->triggers);
        $this->assertSame([], $outcome->conflicts);

        $trigger = $outcome->triggers['KEY-EXIT (PANTALLA)'];

        $this->assertSame(2, $trigger->versions);
        $this->assertSame('P2_15_JUN_202519_30_05', $trigger->pid);
        $this->assertSame('15-JUN-2025 19:30:05', $trigger->modified);
    }

    public function test_conflicto_cuando_el_codigo_diffiere(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'WHEN-BUTTON-PRESSED (B_TAREAS.BTN_OK)',
                ["v := 1;\n"],
                SyntheticFmbFactory::pid(10, '01', 'ENE', '2024', '08', '00', '00'),
            ),
            SyntheticFmbFactory::record(
                'WHEN-BUTTON-PRESSED (B_TAREAS.BTN_OK)',
                ["v := 2;\n", "commit_form;\n"],
                SyntheticFmbFactory::pid(11, '02', 'FEB', '2024', '09', '15', '10'),
            ),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertCount(1, $outcome->triggers);
        $this->assertCount(1, $outcome->conflicts);

        $conflict = $outcome->conflicts[0];

        $this->assertSame('WHEN-BUTTON-PRESSED (B_TAREAS.BTN_OK)', $conflict['name']);
        $this->assertSame(2, $conflict['versions']);
        $this->assertSame('02-FEB-2024 09:15:10', $conflict['kept']);
        $this->assertSame('P11_02_FEB_202409_15_10', $conflict['kept_pid']);
        $this->assertCount(2, $conflict['all']);

        $trigger = $outcome->triggers['WHEN-BUTTON-PRESSED (B_TAREAS.BTN_OK)'];

        $this->assertStringContainsString('v := 2;', $trigger->source);
        $this->assertStringNotContainsString('v := 1;', $trigger->source);
    }

    public function test_registros_antiguos_sin_pid(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::legacyRecord('KEY-EXIT (PANTALLA)', ['exit_form;']),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertCount(1, $outcome->triggers);

        $trigger = $outcome->triggers['KEY-EXIT (PANTALLA)'];

        $this->assertNull($trigger->pid);
        $this->assertSame('(sin fecha)', $trigger->modified);
        $this->assertSame("BEGIN\nexit_form;\nEND;", $trigger->source);
    }

    public function test_legacy_se_omite_si_el_nombre_ya_fue_capturado(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'KEY-EXIT (PANTALLA)',
                ["exit_form;\n"],
                SyntheticFmbFactory::pid(5, '10', 'MAR', '2020', '11', '00', '00'),
            ),
            SyntheticFmbFactory::legacyRecord('KEY-EXIT (PANTALLA)', ["exit_form;\n"]),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertCount(1, $outcome->triggers);
        $this->assertSame(1, $outcome->triggers['KEY-EXIT (PANTALLA)']->versions);
        $this->assertSame('P5_10_MAR_202011_00_00', $outcome->triggers['KEY-EXIT (PANTALLA)']->pid);
    }

    public function test_cuerpo_con_bytes_binarios_se_descarta_sin_fallar(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'WHEN-NEW-FORM-INSTANCE (PANTALLA)',
                ['bloque', "\x01\x02\x03", "sigue;\n"],
                SyntheticFmbFactory::pid(7, '20', 'ABR', '2019', '10', '10', '10'),
            ),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertSame([], $outcome->triggers);
        $this->assertSame([], $outcome->programUnits);
    }

    public function test_datos_truncados_no_revientan(): void
    {
        $data = SyntheticFmbFactory::PAD
            .SyntheticFmbFactory::chunk(SyntheticFmbFactory::pid(9, '01', 'MAY', '2018', '09', '00', '00'))
            .SyntheticFmbFactory::chunk("BEGIN\n")
            .SyntheticFmbFactory::chunk("\nEND;")
            ."\x00\x00\x00\xFFnombre-cortado";

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertSame([], $outcome->triggers);
    }

    public function test_separa_triggers_de_program_units(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'WHEN-NEW-FORM-INSTANCE (PANTALLA)',
                ["null;\n"],
                SyntheticFmbFactory::pid(3, '01', 'ENE', '2021', '08', '00', '00'),
            ),
            SyntheticFmbFactory::record(
                'PKG_TAREAS_UTIL',
                ["procedure p is begin null; end;\n"],
                SyntheticFmbFactory::pid(4, '02', 'ENE', '2021', '09', '00', '00'),
            ),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertSame(['WHEN-NEW-FORM-INSTANCE (PANTALLA)'], array_keys($outcome->triggers));
        $this->assertSame(['PKG_TAREAS_UTIL'], array_keys($outcome->programUnits));
    }

    public function test_el_nombre_se_normaliza_con_trim(): void
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                "  WHEN-NEW-FORM-INSTANCE (PANTALLA) \n",
                ["null;\n"],
                SyntheticFmbFactory::pid(6, '01', 'ENE', '2022', '08', '00', '00'),
            ),
        );

        $outcome = (new CodeRecordScanner($data))->scan();

        $this->assertArrayHasKey('WHEN-NEW-FORM-INSTANCE (PANTALLA)', $outcome->triggers);
    }

    public function test_trigger_kind_interpreta_tipo_y_ambito(): void
    {
        $this->assertTrue(TriggerKind::isTrigger('ON-CHECK-DELETE-MASTER (B_TAREAS)'));
        $this->assertFalse(TriggerKind::isTrigger('PKG_TAREAS_UTIL'));

        $this->assertSame('WHEN-VALIDATE-ITEM', TriggerKind::type('WHEN-VALIDATE-ITEM (B_TAREAS.COD_X)'));
        $this->assertSame('B_TAREAS.COD_X', TriggerKind::scope('WHEN-VALIDATE-ITEM (B_TAREAS.COD_X)'));
        $this->assertSame('PANTALLA', TriggerKind::scope('KEY-CLRFRM (Pantalla)'));
        $this->assertNull(TriggerKind::type('PKG_TAREAS_UTIL'));
    }

    public function test_parse_pid_acepta_meses_en_espanol_e_ingles(): void
    {
        $this->assertSame(
            ['200909140944410000032', '14-SEP-2009 09:44:41'],
            TriggerKind::parsePid('P32_14_SEP_200909_44_41'),
        );

        $this->assertSame(
            ['202401010800000000001', '01-JAN-2024 08:00:00'],
            TriggerKind::parsePid('P1_01_JAN_202408_00_00'),
        );

        $this->assertNull(TriggerKind::parsePid('P1_01_XXX_202408_00_00'));
        $this->assertNull(TriggerKind::parsePid('no-es-un-pid'));
    }
}
