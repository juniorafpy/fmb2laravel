<?php

declare(strict_types=1);

namespace Fmb2Laravel\Tests;

use Fmb2Laravel\Console\Application;
use Fmb2Laravel\Console\IO;
use Fmb2Laravel\Tests\Fixtures\SyntheticFmbFactory;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $workDir = '';

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fmb2laravel-app-'.uniqid();
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workDir);

        parent::tearDown();
    }

    public function test_inspect_json_devuelve_la_ir(): void
    {
        $fmb = $this->writeFmb();

        [$code, $stdout, $stderr] = $this->runApp(['fmb2laravel', 'inspect', $fmb, '--json']);

        $this->assertSame(0, $code);
        $this->assertSame('', $stderr);

        $ir = json_decode($stdout, true);

        $this->assertIsArray($ir);
        $this->assertSame('1.0', $ir['irVersion']);
        $this->assertSame(1, $ir['stats']['triggers']);
    }

    public function test_extract_escribe_todos_los_archivos(): void
    {
        $fmb = $this->writeFmb();
        $out = $this->workDir.DIRECTORY_SEPARATOR.'catalog';

        [$code, $stdout] = $this->runApp(['fmb2laravel', 'extract', $fmb, '--out='.$out, '--module=demo']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('fmb2laravel extract', $stdout);

        foreach ([
            'sample.ir.json',
            'sample.triggers.sql',
            'sample.triggers.json',
            'sample.program_units.json',
            'sample.conflicts.json',
            'sample.reglas.json',
            'sample.reglas.md',
        ] as $name) {
            $this->assertFileExists($out.DIRECTORY_SEPARATOR.$name, $name);
        }

        $triggers = json_decode((string) file_get_contents($out.DIRECTORY_SEPARATOR.'sample.triggers.json'), true);

        $this->assertSame('WHEN-VALIDATE-ITEM (B_TAREAS.COD_ESTADO)', $triggers[0]['name']);
        $this->assertSame('WHEN-VALIDATE-ITEM', $triggers[0]['type']);
        $this->assertSame('B_TAREAS.COD_ESTADO', $triggers[0]['scope']);

        $ir = json_decode((string) file_get_contents($out.DIRECTORY_SEPARATOR.'sample.ir.json'), true);
        $this->assertSame('demo', $ir['module']['name']);

        $reglas = (string) file_get_contents($out.DIRECTORY_SEPARATOR.'sample.reglas.md');
        $this->assertStringContainsString('# Catalogo de reglas - DEMO', $reglas);
    }

    public function test_extract_con_strings_escribe_el_pool(): void
    {
        $fmb = $this->writeFmb();
        $out = $this->workDir.DIRECTORY_SEPARATOR.'catalog-strings';

        [$code] = $this->runApp(['fmb2laravel', 'extract', $fmb, '--out='.$out, '--strings']);

        $this->assertSame(0, $code);
        $this->assertFileExists($out.DIRECTORY_SEPARATOR.'sample.strings.txt');
    }

    public function test_inspect_de_archivo_inexistente_falla_con_mensaje(): void
    {
        [$code, $stdout, $stderr] = $this->runApp(['fmb2laravel', 'inspect', $this->workDir.'/no-existe.fmb']);

        $this->assertSame(1, $code);
        $this->assertSame('', $stdout);
        $this->assertStringContainsString('No existe el archivo', $stderr);
    }

    public function test_sin_archivo_muestra_uso(): void
    {
        [$code, , $stderr] = $this->runApp(['fmb2laravel', 'extract']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('uso: fmb2laravel extract', $stderr);
    }

    public function test_comando_desconocido_falla(): void
    {
        [$code, , $stderr] = $this->runApp(['fmb2laravel', 'frobnicate']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('comando desconocido', $stderr);
    }

    public function test_help_y_version(): void
    {
        [$code, $stdout] = $this->runApp(['fmb2laravel', '--version']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('fmb2laravel', $stdout);

        [$code, $stdout] = $this->runApp(['fmb2laravel', 'help']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Comandos:', $stdout);
    }

    /**
     * @param  array<int, string>  $argv
     * @return array{0: int, 1: string, 2: string}
     */
    private function runApp(array $argv): array
    {
        $stdout = fopen('php://temp', 'r+');
        $stderr = fopen('php://temp', 'r+');

        IO::$stdout = $stdout;
        IO::$stderr = $stderr;

        try {
            $code = (new Application)->run($argv);
        } finally {
            IO::$stdout = null;
            IO::$stderr = null;
        }

        rewind($stdout);
        rewind($stderr);

        return [$code, stream_get_contents($stdout), stream_get_contents($stderr)];
    }

    private function writeFmb(): string
    {
        $data = SyntheticFmbFactory::file(
            SyntheticFmbFactory::record(
                'WHEN-VALIDATE-ITEM (B_TAREAS.COD_ESTADO)',
                ["message('Hola');\n"],
                SyntheticFmbFactory::pid(1, '01', 'ENE', '2024', '08', '00', '00'),
            ),
        );

        $path = $this->workDir.DIRECTORY_SEPARATOR.'sample.fmb';
        file_put_contents($path, $data);

        return $path;
    }

    private function deleteDirectory(string $dir): void
    {
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
