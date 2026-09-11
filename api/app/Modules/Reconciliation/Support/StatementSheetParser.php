<?php

namespace App\Modules\Reconciliation\Support;

use App\Modules\Shared\Support\StringNormalizer;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Extrai transações de um extrato bancário em planilha (XLS/XLSX).
 *
 * Suporta o formato do Bradesco Net Empresa:
 *   Data | Lançamento | Dcto. | Crédito (R$) | Débito (R$) | Saldo (R$)
 *
 * Ignora "SALDO ANTERIOR", "Total", "Lançamentos Futuros" e "Saldos Invest Fácil".
 */
class StatementSheetParser
{
    /** @var list<string> */
    private const STOP_MARKERS = ['total', 'total do dia', 'lancamentos futuros', 'saldos invest facil'];

    /** @var list<string> */
    private const SKIP_MARKERS = ['saldo anterior', 'saldo inicial'];

    /**
     * @return list<array{type: string, date: ?string, value: float, description: ?string, transaction_id: ?string}>
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path, IReader::READ_DATA_ONLY);
        $sheet = $this->resolveSheet($spreadsheet);

        /** @var list<list<mixed>> $rows */
        $rows = $sheet->toArray(null, true, true, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $this->extractTransactions($rows);
    }

    private function resolveSheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);

            if ($this->findHeader($rows) !== null) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{data: int, lancamento: int, dcto: ?int, credito: ?int, debito: ?int}|null
     */
    private function findHeader(array $rows): ?array
    {
        foreach ($rows as $row) {
            $header = $this->mapColumns($row);

            if ($header['data'] !== null && $header['lancamento'] !== null) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     * @return array{data: ?int, lancamento: ?int, dcto: ?int, credito: ?int, debito: ?int}
     */
    private function mapColumns(array $row): array
    {
        $columns = [
            'data' => null,
            'lancamento' => null,
            'dcto' => null,
            'credito' => null,
            'debito' => null,
        ];

        foreach ($row as $index => $cell) {
            if ($cell === null) {
                continue;
            }

            $header = StringNormalizer::sanitize((string) $cell);

            if ($header === '') {
                continue;
            }

            if ($header === 'data' || $header === 'data do lancamento') {
                $columns['data'] ??= $index;
            } elseif ($header === 'lancamento' || $header === 'historico' || $header === 'descricao') {
                $columns['lancamento'] ??= $index;
            } elseif ($header === 'dcto' || $header === 'dcto.' || $header === 'documento') {
                $columns['dcto'] ??= $index;
            } elseif (str_contains($header, 'credito')) {
                $columns['credito'] ??= $index;
            } elseif (str_contains($header, 'debito')) {
                $columns['debito'] ??= $index;
            }
        }

        return $columns;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<array{type: string, date: ?string, value: float, description: ?string, transaction_id: ?string}>
     */
    private function extractTransactions(array $rows): array
    {
        $header = $this->findHeader($rows);

        if ($header === null) {
            throw new RuntimeException('Não foi possível identificar o cabeçalho do extrato (colunas "Data" e "Lançamento").');
        }

        $transactions = [];
        $started = false;

        foreach ($rows as $row) {
            $dataCell = $row[$header['data']] ?? null;
            $lancamentoCell = $row[$header['lancamento']] ?? null;

            if (! $started) {
                if ($this->isHeaderRow($dataCell, $lancamentoCell)) {
                    $started = true;
                }

                continue;
            }

            $description = StringNormalizer::trim((string) $lancamentoCell);
            $dataText = StringNormalizer::trim((string) $dataCell);

            if ($description === '' && $dataText === '') {
                continue;
            }

            $normalized = StringNormalizer::sanitize($description);

            if (in_array($normalized, self::SKIP_MARKERS, true)) {
                continue;
            }

            if (in_array($normalized, self::STOP_MARKERS, true) || str_starts_with($normalized, 'saldos invest')) {
                break;
            }

            if (StringNormalizer::sanitize($dataText) === 'total') {
                break;
            }

            $date = $this->parseDate($dataCell);

            if ($date === null) {
                continue;
            }

            $credit = $header['credito'] !== null ? $this->parseMoney($row[$header['credito']] ?? null) : 0.0;
            $debit = $header['debito'] !== null ? $this->parseMoney($row[$header['debito']] ?? null) : 0.0;

            if (abs($credit) > 0) {
                $type = 'credit';
                $value = abs($credit);
            } elseif (abs($debit) > 0) {
                $type = 'debit';
                $value = abs($debit);
            } else {
                continue;
            }

            $doc = $header['dcto'] !== null
                ? StringNormalizer::trim((string) ($row[$header['dcto']] ?? ''))
                : '';

            $transactions[] = [
                'type' => $type,
                'date' => $date,
                'value' => round($value, 2),
                'description' => $description === '' ? null : $description,
                'transaction_id' => md5(implode('|', [$date, $type, number_format($value, 2, '.', ''), $description, $doc])),
            ];
        }

        return $transactions;
    }

    private function isHeaderRow(mixed $dataCell, mixed $lancamentoCell): bool
    {
        $data = StringNormalizer::sanitize((string) $dataCell);
        $lancamento = StringNormalizer::sanitize((string) $lancamentoCell);

        return in_array($data, ['data', 'data do lancamento'], true)
            && in_array($lancamento, ['lancamento', 'historico', 'descricao'], true);
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $s = StringNormalizer::trim((string) $value);

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $s, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];

            if (strlen($m[3]) === 2) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    private function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = str_replace(['R$', 'r$', ' '], '', StringNormalizer::trim((string) $value));

        if ($s === '' || $s === '-') {
            return 0.0;
        }

        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
        }

        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : 0.0;
    }
}
