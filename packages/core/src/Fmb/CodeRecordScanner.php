<?php

declare(strict_types=1);

namespace Fmb2Laravel\Fmb;

/**
 * Lector heuristico de registros de codigo en un .FMB de Oracle Forms.
 *
 * Port fiel de fmb_parse.py (prototipo validado contra un formulario real):
 *
 *     [len]"P<id>_<DD>_<MMM>_<YYYY><HH>_<MM>_<SS>"   <- id + fecha de compilacion
 *     [len]"BEGIN\n" ... [len]chunk ... [len]"\nEND;"  <- cuerpo PL/SQL
 *     [len]"WHEN-VALIDATE-ITEM (BLOQUE.ITEM)"           <- nombre del objeto
 *
 * El archivo conserva versiones antiguas de cada objeto: se conserva la mas
 * reciente y se reportan conflictos cuando dos versiones difieren.
 */
final class CodeRecordScanner
{
    private const CANDIDATE_PATTERN = '/\x00\x00\x00[\x00-\xff]P\d{1,3}_\d{2}_[A-Z]{3}_\d{4}\d{2}_\d{2}_\d{2}/';

    private const ANCHOR_PATTERN = '/\x00\x00\x00[\x05-\x09](?:BEGIN|DECLARE)\n/';

    private const END_MARKERS = ["\nEND;", 'END;', "\nend;"];

    private const MAX_READS_PER_BODY = 2000;

    private const MAX_TEXT_LENGTH = 500000;

    private const TRIM_CHARS = " \t\n\r\0\x0B\x0C";

    /** Clave de orden para registros antiguos sin fecha. */
    private const ZERO_SORT_KEY = '000000000000000000000';

    public function __construct(private readonly string $data) {}

    public function scan(): ScanOutcome
    {
        $records = $this->parseRecords();

        [$unique, $conflicts] = $this->dedupe($records);

        $triggers = [];
        $programUnits = [];

        foreach ($unique as $name => $record) {
            if (TriggerKind::isTrigger($name)) {
                $triggers[$name] = $record;
            } else {
                $programUnits[$name] = $record;
            }
        }

        ksort($triggers);
        ksort($programUnits);

        return new ScanOutcome($triggers, $programUnits, $conflicts, count($records));
    }

    /**
     * @return array<int, CodeRecord>
     */
    public function parseRecords(): array
    {
        $records = $this->parseCandidateRecords();
        $knownNames = [];

        foreach ($records as $record) {
            $knownNames[$record->name] = true;
        }

        return [...$records, ...$this->parseFallbackRecords($knownNames)];
    }

    /**
     * @return array<int, CodeRecord>
     */
    private function parseCandidateRecords(): array
    {
        $records = [];

        if (preg_match_all(self::CANDIDATE_PATTERN, $this->data, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as [, $offset]) {
            $record = $this->parseCandidateAt($offset);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function parseCandidateAt(int $offset): ?CodeRecord
    {
        $header = $this->readLengthString($offset);

        if ($header === null) {
            return null;
        }

        [, $pidText, $cursor] = $header;

        $pidDate = TriggerKind::parsePid($pidText);

        if ($pidDate === null) {
            return null;
        }

        $body = $this->readBody($cursor);

        if ($body === null) {
            return null;
        }

        [$source, $cursor] = $body;

        $name = $this->readLengthString($cursor);

        if ($name === null) {
            return null;
        }

        $name = trim($name[1], self::TRIM_CHARS);

        if ($name === '') {
            return null;
        }

        return new CodeRecord(
            offset: $offset,
            pid: $pidText,
            modifiedSort: $pidDate[0],
            modified: $pidDate[1],
            name: $name,
            source: $source,
        );
    }

    /**
     * Registros antiguos sin encabezado "P<id>": el cuerpo arranca en el
     * propio ancla ("BEGIN\n" / "DECLARE\n") y solo se aceptan nombres
     * que no hayan sido capturados ya.
     *
     * @param  array<string, bool>  $knownNames
     * @return array<int, CodeRecord>
     */
    private function parseFallbackRecords(array $knownNames): array
    {
        $records = [];

        if (preg_match_all(self::ANCHOR_PATTERN, $this->data, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as [, $offset]) {
            $body = $this->readBody($offset);

            if ($body === null) {
                continue;
            }

            [$source, $cursor] = $body;

            $name = $this->readLengthString($cursor);

            if ($name === null) {
                continue;
            }

            $name = trim($name[1], self::TRIM_CHARS);

            if ($name === '' || isset($knownNames[$name])) {
                continue;
            }

            $records[] = new CodeRecord(
                offset: $offset,
                pid: null,
                modifiedSort: self::ZERO_SORT_KEY,
                modified: '(sin fecha)',
                name: $name,
                source: $source,
            );
        }

        return $records;
    }

    /**
     * Lee chunks consecutivos hasta el marcador de cierre del cuerpo.
     *
     * @return array{0: string, 1: int}|null [codigo, cursor tras el cierre]
     */
    private function readBody(int $cursor): ?array
    {
        $chunks = [];

        for ($reads = 0; $reads < self::MAX_READS_PER_BODY; $reads++) {
            $chunk = $this->readLengthString($cursor);

            if ($chunk === null) {
                return null;
            }

            [, $text, $cursor] = $chunk;

            $chunks[] = $text;

            if (in_array($text, self::END_MARKERS, true)) {
                return [trim(implode('', $chunks), self::TRIM_CHARS), $cursor];
            }
        }

        return null;
    }

    /**
     * Lee una cadena con longitud de 4 bytes big-endian si es texto valido.
     *
     * @return array{0: int, 1: string, 2: int}|null [longitud, texto, cursor]
     */
    private function readLengthString(int $offset): ?array
    {
        $dataLength = strlen($this->data);

        if ($offset < 0 || $offset + 4 > $dataLength) {
            return null;
        }

        $length = unpack('N', substr($this->data, $offset, 4))[1];

        if ($length === 0 || $length > self::MAX_TEXT_LENGTH || $offset + 4 + $length > $dataLength) {
            return null;
        }

        $raw = substr($this->data, $offset + 4, $length);

        if (! Text::isDecodable($raw)) {
            return null;
        }

        return [$length, Text::decode($raw), $offset + 4 + $length];
    }

    /**
     * Agrupa por nombre y conserva la version mas reciente de cada objeto.
     *
     * @param  array<int, CodeRecord>  $records
     * @return array{0: array<string, CodeRecord>, 1: array<int, array<string, mixed>>}
     */
    private function dedupe(array $records): array
    {
        $groups = [];

        foreach ($records as $record) {
            $groups[$record->name][] = $record;
        }

        $unique = [];
        $conflicts = [];

        foreach ($groups as $name => $group) {
            usort(
                $group,
                static fn (CodeRecord $a, CodeRecord $b): int => strcmp($b->modifiedSort, $a->modifiedSort),
            );

            $sources = array_unique(array_map(static fn (CodeRecord $record): string => $record->source, $group));

            $newest = $group[0];
            $newest->versions = count($group);

            if (count($sources) > 1) {
                $conflicts[] = [
                    'name' => $name,
                    'versions' => count($group),
                    'kept' => $newest->modified,
                    'kept_pid' => $newest->pid,
                    'all' => array_map(static fn (CodeRecord $record): array => [
                        'pid' => $record->pid,
                        'modified' => $record->modified,
                        'lines' => $record->lines(),
                    ], $group),
                ];
            }

            $unique[$name] = $newest;
        }

        return [$unique, $conflicts];
    }
}
