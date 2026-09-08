<?php

namespace App\Modules\Account\Support;

use Normalizer;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Lê linhas de um XLSX sem materializar o workbook inteiro no PhpSpreadsheet.
 *
 * Planilhas da Apolar marcam dimensão até a última linha do Excel (~1 milhão)
 * e a aba de tipos chega a ter uma célula por linha. O reader nativo explode a memória.
 */
class XlsxRowReader
{
    private const EMPTY_STREAK_LIMIT = 25;

    private const MAX_COLUMNS = 40;

    /**
     * @return list<list<mixed>>
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
        }

        try {
            $sheets = $this->listSheets($zip);
            $sharedStrings = $this->loadSharedStrings($zip);
            $sheetPath = $this->resolveSheetPath($path, $sheets, $sharedStrings);

            return $this->readSheetRows($path, $sheetPath, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    public static function canRead(string $path): bool
    {
        if (! is_readable($path)) {
            return false;
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $ok = $zip->locateName('xl/workbook.xml') !== false
            || $zip->locateName('/xl/workbook.xml') !== false;

        $zip->close();

        return $ok;
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function listSheets(ZipArchive $zip): array
    {
        $workbook = $this->zipContents($zip, 'xl/workbook.xml');
        $rels = $this->parseRels($this->zipContents($zip, 'xl/_rels/workbook.xml.rels'));

        $sheets = [];
        $reader = new XMLReader;
        $reader->xml($workbook, null, LIBXML_NONET | LIBXML_COMPACT);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'sheet') {
                continue;
            }

            $name = (string) $reader->getAttribute('name');
            $rid = (string) ($reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
                ?: $reader->getAttribute('r:id')
                ?: $reader->getAttribute('id'));

            if ($name === '' || $rid === '' || ! isset($rels[$rid])) {
                continue;
            }

            $sheets[] = [
                'name' => $name,
                'path' => $this->normalizeInnerPath($rels[$rid]),
            ];
        }

        $reader->close();

        if ($sheets === []) {
            throw new RuntimeException('A planilha não possui abas.');
        }

        return $sheets;
    }

    /**
     * @return array<string, string>
     */
    private function parseRels(string $xml): array
    {
        $rels = [];
        $reader = new XMLReader;
        $reader->xml($xml, null, LIBXML_NONET | LIBXML_COMPACT);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Relationship') {
                continue;
            }

            $id = (string) $reader->getAttribute('Id');
            $target = (string) $reader->getAttribute('Target');

            if ($id !== '' && $target !== '') {
                $rels[$id] = $target;
            }
        }

        $reader->close();

        return $rels;
    }

    /**
     * @return list<string>
     */
    private function loadSharedStrings(ZipArchive $zip): array
    {
        $xml = $this->zipContents($zip, 'xl/sharedStrings.xml', false);

        if ($xml === null) {
            return [];
        }

        $strings = [];
        $reader = new XMLReader;
        $reader->xml($xml, null, LIBXML_NONET | LIBXML_COMPACT);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $strings[] = $this->readStringItem($reader);
            }
        }

        $reader->close();

        return $strings;
    }

    private function readStringItem(XMLReader $reader): string
    {
        $text = '';
        $depth = $reader->depth;

        if ($reader->isEmptyElement) {
            return '';
        }

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si' && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                $text .= $reader->readString();
            }
        }

        return $text;
    }

    /**
     * @param  list<array{name: string, path: string}>  $sheets
     * @param  list<string>  $sharedStrings
     */
    private function resolveSheetPath(string $xlsxPath, array $sheets, array $sharedStrings): string
    {
        foreach ($sheets as $sheet) {
            if ($this->normalize($sheet['name']) === 'base de dados') {
                return $sheet['path'];
            }
        }

        foreach ($sheets as $sheet) {
            $header = $this->readSheetRows($xlsxPath, $sheet['path'], $sharedStrings, 1)[0] ?? [];

            if ($this->looksLikeDataSheet($header)) {
                return $sheet['path'];
            }
        }

        return $sheets[0]['path'];
    }

    /**
     * @param  list<mixed>  $header
     */
    private function looksLikeDataSheet(array $header): bool
    {
        $headers = array_map(fn ($cell) => $this->normalize($cell), $header);
        $joined = implode('|', $headers);

        if (! str_contains($joined, 'grupo')) {
            return false;
        }

        return str_contains($joined, 'historico')
            || str_contains($joined, 'tipo de despesa')
            || str_contains($joined, 'vencimento')
            || str_contains($joined, 'debito');
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<list<mixed>>
     */
    private function readSheetRows(string $xlsxPath, string $sheetPath, array $sharedStrings, ?int $maxRows = null): array
    {
        $reader = $this->openSheetXml($xlsxPath, $sheetPath);
        $rows = [];
        $emptyStreak = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $row = $this->readRow($reader, $sharedStrings);

                if ($this->isBlankRow($row)) {
                    $emptyStreak++;

                    if ($rows !== [] && $emptyStreak >= self::EMPTY_STREAK_LIMIT) {
                        break;
                    }

                    continue;
                }

                $emptyStreak = 0;
                $rows[] = $row;

                if ($maxRows !== null && count($rows) >= $maxRows) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<mixed>
     */
    private function readRow(XMLReader $reader, array $sharedStrings): array
    {
        $cells = [];
        $maxIndex = -1;

        if ($reader->isEmptyElement) {
            return [];
        }

        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row' && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            $ref = (string) $reader->getAttribute('r');
            $type = (string) $reader->getAttribute('t');
            $index = $ref !== '' ? $this->columnIndex($ref) : $maxIndex + 1;
            $value = $this->readCellValue($reader, $type, $sharedStrings);

            if ($index < 0 || $index >= self::MAX_COLUMNS) {
                continue;
            }

            $cells[$index] = $value;
            $maxIndex = max($maxIndex, $index);
        }

        if ($maxIndex < 0) {
            return [];
        }

        $row = array_fill(0, $maxIndex + 1, null);

        foreach ($cells as $index => $value) {
            $row[$index] = $value;
        }

        return $row;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function readCellValue(XMLReader $reader, string $type, array $sharedStrings): mixed
    {
        if ($reader->isEmptyElement) {
            return null;
        }

        $depth = $reader->depth;
        $raw = null;
        $inline = '';

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c' && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->localName === 'v' || $reader->localName === 't') {
                $text = $reader->readString();

                if ($reader->localName === 't') {
                    $inline .= $text;
                } else {
                    $raw = $text;
                }
            }
        }

        if ($type === 'inlineStr' || $type === 'str') {
            $value = $inline !== '' ? $inline : (string) $raw;

            return $value === '' ? null : $value;
        }

        if ($type === 's') {
            $index = is_numeric($raw) ? (int) $raw : null;

            return $index !== null ? ($sharedStrings[$index] ?? null) : null;
        }

        if ($type === 'b') {
            return $raw === '1';
        }

        if ($raw === null || $raw === '') {
            return $inline !== '' ? $inline : null;
        }

        return is_numeric($raw) ? (float) $raw : $raw;
    }

    private function openSheetXml(string $xlsxPath, string $sheetPath): XMLReader
    {
        $reader = new XMLReader;
        $uri = 'zip://'.$xlsxPath.'#'.$sheetPath;

        if (@$reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            return $reader;
        }

        $zip = new ZipArchive;

        if ($zip->open($xlsxPath) !== true) {
            throw new RuntimeException('Não foi possível reler a planilha XLSX.');
        }

        $xml = $this->zipContents($zip, $sheetPath);
        $zip->close();

        $fallback = new XMLReader;

        if (! $fallback->xml($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Não foi possível ler a aba da planilha.');
        }

        return $fallback;
    }

    private function zipContents(ZipArchive $zip, string $path, bool $required = true): ?string
    {
        foreach ($this->pathCandidates($path) as $candidate) {
            $contents = $zip->getFromName($candidate);

            if ($contents !== false) {
                return $contents;
            }
        }

        if (! $required) {
            return null;
        }

        throw new RuntimeException("Arquivo interno da planilha não encontrado: {$path}");
    }

    /**
     * @return list<string>
     */
    private function pathCandidates(string $path): array
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $candidates = [$path, '/'.$path];

        if (! str_starts_with($path, 'xl/')) {
            $candidates[] = 'xl/'.$path;
            $candidates[] = '/xl/'.$path;
        }

        return array_values(array_unique($candidates));
    }

    private function normalizeInnerPath(string $target): string
    {
        $target = ltrim(str_replace('\\', '/', $target), '/');

        if (str_starts_with($target, 'xl/')) {
            return $target;
        }

        return 'xl/'.$target;
    }

    private function columnIndex(string $cellRef): int
    {
        if (! preg_match('/^([A-Za-z]+)/', $cellRef, $matches)) {
            return -1;
        }

        $column = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0, $length = strlen($column); $i < $length; $i++) {
            $index = ($index * 26) + (ord($column[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalize(mixed $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);

        if ($decomposed === false) {
            return $text;
        }

        return preg_replace('/[\x{0300}-\x{036F}]/u', '', $decomposed) ?? $text;
    }
}
