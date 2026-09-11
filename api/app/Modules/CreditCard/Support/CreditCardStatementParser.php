<?php

namespace App\Modules\CreditCard\Support;

use App\Modules\Shared\Support\StringNormalizer;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Extrai as compras de uma fatura de cartão de crédito (XLS/XLSX).
 *
 * Suporta o formato de "Fatura Fechada" do Itaú:
 *   Data | Lançamento | Parcelamento | Valor | Titularidade | Nome | Tipo do cartão | Número do cartão
 *
 * A coluna "Data" costuma vir como número serial do Excel. Linhas de
 * pagamento/estorno (valor <= 0) e marcadores como "Pagamento Efetuado",
 * "Subtotal" e "Importante saber" são ignorados. Cada linha vira uma compra.
 */
class CreditCardStatementParser
{
    /** @var list<string> */
    private const SKIP_DESCRIPTIONS = ['pagamento efetuado', 'pagamento', 'subtotal', 'total', 'importante saber'];

    /**
     * @return list<array{purchase_date: string, description: string, value: float}>
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path, IReader::READ_DATA_ONLY);
        $sheet = $this->resolveSheet($spreadsheet);

        /** @var list<list<mixed>> $rows */
        $rows = $sheet->toArray(null, false, false, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $this->extractPurchases($rows);
    }

    private function resolveSheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, false, false, false);

            if ($this->findHeader($rows) !== null) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{data: ?int, lancamento: ?int, valor: ?int}
     */
    private function findHeader(array $rows): ?array
    {
        foreach ($rows as $row) {
            $header = $this->mapColumns($row);

            if ($header['data'] !== null && $header['lancamento'] !== null && $header['valor'] !== null) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     * @return array{data: ?int, lancamento: ?int, valor: ?int}
     */
    private function mapColumns(array $row): array
    {
        $columns = ['data' => null, 'lancamento' => null, 'valor' => null];

        foreach ($row as $index => $cell) {
            if ($cell === null) {
                continue;
            }

            $header = StringNormalizer::sanitize((string) $cell);

            if ($header === '') {
                continue;
            }

            if ($header === 'data' || $header === 'data da compra') {
                $columns['data'] ??= $index;
            } elseif ($header === 'lancamento' || $header === 'lancamentos' || $header === 'historico' || $header === 'descricao') {
                $columns['lancamento'] ??= $index;
            } elseif ($header === 'valor' || $header === 'valor (r$)') {
                $columns['valor'] ??= $index;
            }
        }

        return $columns;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<array{purchase_date: string, description: string, value: float}>
     */
    private function extractPurchases(array $rows): array
    {
        $header = $this->findHeader($rows);

        if ($header === null) {
            throw new RuntimeException('Não foi possível identificar o cabeçalho da fatura (colunas "Data", "Lançamento" e "Valor").');
        }

        $purchases = [];
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

            if ($description === '') {
                continue;
            }

            $normalized = StringNormalizer::sanitize($description);

            if (in_array($normalized, self::SKIP_DESCRIPTIONS, true) || str_starts_with($normalized, 'subtotal')) {
                if (in_array($normalized, ['subtotal', 'total', 'importante saber'], true)) {
                    break;
                }

                continue;
            }

            $date = $this->parseDate($dataCell);

            if ($date === null) {
                continue;
            }

            $value = $this->parseMoney($row[$header['valor']] ?? null);

            if ($value <= 0) {
                continue;
            }

            $purchases[] = [
                'purchase_date' => $date,
                'description' => $description,
                'value' => round($value, 2),
            ];
        }

        return $purchases;
    }

    private function isHeaderRow(mixed $dataCell, mixed $lancamentoCell): bool
    {
        $data = StringNormalizer::sanitize((string) $dataCell);
        $lancamento = StringNormalizer::sanitize((string) $lancamentoCell);

        return $data === 'data' && in_array($lancamento, ['lancamento', 'lancamentos', 'historico', 'descricao'], true);
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
