<?php

declare(strict_types=1);

namespace Fmb2Laravel\Fmb;

/**
 * Un registro de codigo recuperado del .FMB (trigger o program unit).
 */
final class CodeRecord
{
    public function __construct(
        public readonly int $offset,
        public readonly ?string $pid,
        public readonly string $modifiedSort,
        public readonly string $modified,
        public readonly string $name,
        public readonly string $source,
        public int $versions = 1,
    ) {}

    public function lines(): int
    {
        return substr_count($this->source, "\n") + 1;
    }

    public function toArray(): array
    {
        return [
            'offset' => $this->offset,
            'pid' => $this->pid,
            'modified' => $this->modified,
            'name' => $this->name,
            'versions' => $this->versions,
            'source' => $this->source,
        ];
    }
}
