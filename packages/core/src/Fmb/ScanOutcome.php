<?php

declare(strict_types=1);

namespace Fmb2Laravel\Fmb;

/**
 * Resultado completo de escanear un .FMB.
 */
final class ScanOutcome
{
    /**
     * @param  array<string, CodeRecord>  $triggers  nombre => registro (version mas nueva)
     * @param  array<string, CodeRecord>  $programUnits  nombre => registro (version mas nueva)
     * @param  array<int, array<string, mixed>>  $conflicts
     * @param  int  $totalRecords  registros crudos antes de deduplicar
     */
    public function __construct(
        public readonly array $triggers,
        public readonly array $programUnits,
        public readonly array $conflicts,
        public readonly int $totalRecords,
    ) {}
}
