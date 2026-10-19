<?php

declare(strict_types=1);

namespace Fmb2Laravel\Fmb;

use RuntimeException;

/**
 * Archivo .FMB cargado en memoria.
 */
final class FmbFile
{
    private function __construct(
        public readonly string $path,
        public readonly string $data,
    ) {}

    public static function open(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("No existe el archivo: {$path}");
        }

        if (! is_readable($path)) {
            throw new RuntimeException("No se puede leer el archivo: {$path}");
        }

        $data = file_get_contents($path);

        if ($data === false) {
            throw new RuntimeException("No se pudo leer el archivo: {$path}");
        }

        return new self($path, $data);
    }

    public function size(): int
    {
        return strlen($this->data);
    }

    public function sha256(): string
    {
        return hash('sha256', $this->data);
    }

    public function stem(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }
}
