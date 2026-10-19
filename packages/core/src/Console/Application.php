<?php

declare(strict_types=1);

namespace Fmb2Laravel\Console;

use Fmb2Laravel\Version;
use Throwable;

final class Application
{
    /**
     * @param  array<int, string>  $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;
        $args = array_slice($argv, 2);

        return match ($command) {
            null, 'help', '--help', '-h' => $this->help(),
            'version', '--version', '-v' => $this->version(),
            'inspect' => $this->safe(static fn (): int => (new InspectCommand)->run($args)),
            'extract' => $this->safe(static fn (): int => (new ExtractCommand)->run($args)),
            'strings' => $this->safe(static fn (): int => (new StringsCommand)->run($args)),
            default => $this->unknown($command),
        };
    }

    private function safe(callable $command): int
    {
        try {
            return $command();
        } catch (Throwable $exception) {
            IO::err('error: '.$exception->getMessage());

            return 1;
        }
    }

    private function unknown(string $command): int
    {
        IO::err("error: comando desconocido \"{$command}\"");
        IO::err();
        $this->help();

        return 1;
    }

    private function version(): int
    {
        IO::out(Version::NAME.' '.Version::VERSION);

        return 0;
    }

    private function help(): int
    {
        IO::out(Version::NAME.' '.Version::VERSION.' — lector heuristico de Oracle Forms (.fmb)');
        IO::out();
        IO::out('uso: fmb2laravel <comando> [opciones] <archivo.fmb>');
        IO::out();
        IO::out('Comandos:');
        IO::out('  inspect <archivo.fmb> [--json]           Diagnostico del modulo (no escribe archivos)');
        IO::out('  extract <archivo.fmb> [--out=DIR]        Catalogo completo: IR, triggers, reglas');
        IO::out('          [--module=NAME] [--config=FILE] [--strings]');
        IO::out('  strings <archivo.fmb> [--out=DIR]        Volcado del pool de cadenas');
        IO::out('          [--min=4]');
        IO::out('  version                                  Muestra la version');
        IO::out();
        IO::out('Ejemplos:');
        IO::out('  fmb2laravel inspect forms/MIFORM.fmb');
        IO::out('  fmb2laravel extract forms/MIFORM.fmb --out=catalog');

        return 0;
    }
}
