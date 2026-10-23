<?php

declare(strict_types=1);

/**
 * Regenera tests/Fixtures/demo.fmb (fixture publico).
 *
 * Uso:
 *     php tests/Fixtures/generate-demo.php
 *
 * El archivo generado imita el empaquetado binario de Oracle Forms 6i y
 * cubre los casos del lector: registros modernos, uno antiguo sin fecha,
 * un conflicto de versiones, boilerplate y un program unit. No contiene
 * datos de ninguna empresa.
 */
require __DIR__.'/../../vendor/autoload.php';

use Fmb2Laravel\Tests\Fixtures\SyntheticFmbFactory as F;

$data = F::file(
    F::record(
        'WHEN-NEW-FORM-INSTANCE (PANTALLA)',
        [
            "app_window.set_window_position('DEMO', 'FIRST_WINDOW');\n",
            "inicializa_globales;\n",
        ],
        F::pid(1, '10', 'MAR', '2021', '09', '15', '00'),
    ),
    F::record(
        'KEY-CLRFRM (PANTALLA)',
        [
            "clear_form;\n",
            "inicializa_globales;\n",
        ],
        F::pid(2, '10', 'MAR', '2021', '09', '20', '00'),
    ),
    F::record(
        'WHEN-VALIDATE-ITEM (B_DEMO.STATUS)',
        [
            "SELECT descripcion INTO v_desc\n",
            "  FROM demo_statuses\n",
            " WHERE status = :b_demo.status;\n",
            "if v_desc is null then\n",
            "  message('Estado invalido');\n",
            "end if;\n",
        ],
        F::pid(3, '11', 'MAR', '2021', '10', '05', '00'),
    ),
    F::record(
        'WHEN-BUTTON-PRESSED (B_DEMO.BTN_GRABAR)',
        [
            "INSERT INTO demo_records (cod, descripcion) VALUES (:b_demo.cod, :b_demo.descripcion);\n",
            "commit_form;\n",
        ],
        F::pid(4, '12', 'MAR', '2021', '11', '30', '00'),
    ),
    F::record(
        'WHEN-LIST-CHANGED (B_DEMO.STATUS)',
        [
            "do_key('list_values');\n",
            "v := get_item_property('b_demo.status', lov_name);\n",
        ],
        F::pid(5, '13', 'MAR', '2021', '12', '00', '00'),
    ),
    // Conflicto: dos versiones con codigo distinto (se conserva la nueva).
    F::record(
        'WHEN-BUTTON-PRESSED (B_DEMO.BTN_BORRAR)',
        [
            "delete_record;\n",
        ],
        F::pid(6, '14', 'MAR', '2021', '08', '00', '00'),
    ),
    F::record(
        'WHEN-BUTTON-PRESSED (B_DEMO.BTN_BORRAR)',
        [
            "if :b_demo.cod is null then\n",
            "  message('No hay registro que borrar');\n",
            "else\n",
            "  delete_record;\n",
            "end if;\n",
        ],
        F::pid(7, '15', 'MAR', '2021', '08', '30', '00'),
    ),
    // Registro antiguo (sin encabezado P<id>).
    F::legacyRecord(
        'ON-CHECK-DELETE-MASTER (B_DEMO)',
        [
            "alerta('Operacion no permitida: existen detalles asociados');\n",
        ],
    ),
    // Program unit.
    F::record(
        'PKG_DEMO_UTIL',
        [
            "procedure inicializa_globales is\n",
            "begin\n",
            "  null;\n",
            "end;\n",
        ],
        F::pid(8, '16', 'MAR', '2021', '10', '00', '00'),
    ),
);

$target = __DIR__.'/demo.fmb';
file_put_contents($target, $data);

echo 'demo.fmb regenerado: '.number_format(strlen($data)).' bytes'.PHP_EOL;
