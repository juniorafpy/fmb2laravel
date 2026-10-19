<?php

declare(strict_types=1);

namespace Fmb2Laravel\Console;

use Fmb2Laravel\Catalog\RuleAnalyzer;
use Fmb2Laravel\Fmb\CodeRecordScanner;
use Fmb2Laravel\Fmb\FmbFile;
use Fmb2Laravel\Fmb\Text;
use Fmb2Laravel\Ir\IrBuilder;
use Fmb2Laravel\Version;

final class InspectCommand
{
    /**
     * @param  array<int, string>  $args
     */
    public function run(array $args): int
    {
        $json = Cli::flag($args, 'json');
        $file = Cli::positional($args, ['json'])[0] ?? null;

        if ($file === null) {
            IO::err('uso: fmb2laravel inspect <archivo.fmb> [--json]');

            return 1;
        }

        $fmb = FmbFile::open($file);
        $outcome = (new CodeRecordScanner($fmb->data))->scan();

        $analyzer = new RuleAnalyzer;
        $irBuilder = new IrBuilder($analyzer);
        $module = $fmb->stem();
        $ir = $irBuilder->build($fmb, $outcome, $module);

        if ($json) {
            IO::out(rtrim($irBuilder->encode($ir), "\n"));

            return 0;
        }

        $reglas = $analyzer->build($module, $outcome);

        IO::out(Version::NAME.' '.Version::VERSION.' — inspect');
        IO::out();
        IO::out('Archivo            : '.$fmb->path);
        IO::out('Tamano             : '.number_format($fmb->size()).' bytes');
        IO::out('sha256             : '.$fmb->sha256());
        IO::out();
        IO::out(sprintf('Registros crudos   : %d', $outcome->totalRecords));
        IO::out(sprintf('Triggers           : %d', count($outcome->triggers)));
        IO::out(sprintf('Program units      : %d', count($outcome->programUnits)));
        IO::out(sprintf('Conflictos         : %d', count($outcome->conflicts)));
        IO::out(sprintf('Cadenas (pool)     : %d', count(Text::pool($fmb->data))));
        IO::out(sprintf('Triggers con logica: %d', $analyzer->logicCount($reglas)));
        IO::out(sprintf('Boilerplate        : %d', $analyzer->boilerplateCount($reglas)));

        IO::out();
        IO::out('Bloques:');

        foreach ($ir['blocks'] as $block) {
            IO::out(sprintf(
                '  %-14s -> %-22s %d triggers, %d items',
                $block['name'],
                $block['candidateTable'] ?? '(desconocida)',
                $block['triggerCount'],
                $block['itemCount'],
            ));
        }

        $formLevel = 0;

        foreach ($ir['triggers'] as $trigger) {
            if ($trigger['block'] === null) {
                $formLevel++;
            }
        }

        if ($formLevel > 0) {
            IO::out(sprintf('  (nivel formulario)                  %d triggers', $formLevel));
        }

        IO::out();
        IO::out('Tablas referenciadas: '.implode(', ', $ir['tables']['referenced']));
        IO::out('Tablas escritas     : '.implode(', ', $ir['tables']['written']));

        if ($ir['messages'] !== []) {
            IO::out();
            IO::out(sprintf('Mensajes (%d):', count($ir['messages'])));

            foreach (array_slice($ir['messages'], 0, 10) as $message) {
                IO::out('  - '.$message);
            }

            if (count($ir['messages']) > 10) {
                IO::out(sprintf('  ... y %d mas', count($ir['messages']) - 10));
            }
        }

        if ($ir['warnings'] !== []) {
            IO::out();
            IO::out('Avisos:');

            foreach ($ir['warnings'] as $warning) {
                IO::out('  ['.$warning['code'].'] '.$warning['message']);
            }
        }

        IO::out();
        IO::out("Para el catalogo completo: fmb2laravel extract \"{$file}\" --out=catalog");

        return 0;
    }
}
