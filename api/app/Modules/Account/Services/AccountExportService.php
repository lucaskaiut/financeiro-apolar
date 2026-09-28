<?php

namespace App\Modules\Account\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AllocationMode;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\CreditCard\Models\CreditCard;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountExportService
{
    private const HEADERS = [
        'Tipo',
        'Descrição',
        'Cliente/Fornecedor',
        'Categoria',
        'Subcategoria',
        'Centro de custo',
        'Conta bancária',
        'Cartão',
        'Compra',
        'Previsto',
        'Vencimento',
        'Baixa',
        'Status',
        'Observação',
        'Valor',
        'Liquidado',
        'Restante',
    ];

    private const COLUMN_COUNT = 17;

    private const CURRENCY_START_COLUMN = 15;

    public function __construct(private readonly AccountService $accounts) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportXlsx(array $filters): StreamedResponse
    {
        $accounts = $this->accounts->filteredQuery($filters)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return $this->streamXlsx('contas-a-pagar-receber.xlsx', 'Contas', function (Worksheet $sheet) use ($accounts, $filters): void {
            $row = $this->applyTitleBlock($sheet, 'Contas a pagar e receber', $this->filterLines($filters));
            $headerRow = $row;
            $sheet->fromArray(self::HEADERS, null, "A{$row}");
            $row++;

            $dataStartRow = $row;

            foreach ($accounts as $account) {
                $sheet->fromArray($this->row($account), null, "A{$row}");
                $row++;
            }

            $dataEndRow = $row - 1;

            $this->applyHeaderRow($sheet, $headerRow);
            $this->applyDataArea($sheet, $dataStartRow, $dataEndRow);
            $this->applyColumnWidths($sheet);

            $totalRow = $row + 1;
            $sheet->setCellValue("A{$totalRow}", 'TOTAIS');
            $sheet->setCellValue('O'.$totalRow, $this->xlsxMoney($accounts->sum(fn (FinancialAccount $account) => (float) $account->value)));
            $sheet->setCellValue('P'.$totalRow, $this->xlsxMoney($accounts->sum(fn (FinancialAccount $account) => $account->settled_amount)));
            $sheet->setCellValue('Q'.$totalRow, $this->xlsxMoney($accounts->sum(fn (FinancialAccount $account) => $account->remaining_amount)));
            $this->applyTotalsRow($sheet, $totalRow);
        });
    }

    /**
     * @return list<string|int|float|null>
     */
    private function row(FinancialAccount $account): array
    {
        $isSplit = $account->allocation_mode === AllocationMode::Split;

        $category = $isSplit
            ? $account->allocations
                ->map(fn ($allocation) => $allocation->category?->name)
                ->filter()
                ->unique()
                ->implode(' / ')
            : $account->category?->name;

        return [
            $account->type?->value === 'receivable' ? 'A receber' : 'A pagar',
            $account->description,
            $account->counterparty,
            $category,
            $isSplit ? null : $account->subcategory?->name,
            $account->costCenter?->name,
            $account->bankAccount?->name,
            $account->creditCard?->name,
            $account->purchase_date?->format('d/m/Y'),
            $account->expected_date?->format('d/m/Y'),
            $account->due_date?->format('d/m/Y'),
            $account->paid_date?->format('d/m/Y'),
            $account->status?->label(),
            $account->observation,
            $this->xlsxMoney($account->value),
            $this->xlsxMoney($account->settled_amount),
            $this->xlsxMoney($account->remaining_amount),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function filterLines(array $filters): array
    {
        $lines = [];

        if (filled($filters['search'] ?? null)) {
            $lines[] = "Busca: {$filters['search']}";
        }

        if (filled($filters['type'] ?? null)) {
            $lines[] = $filters['type'] === 'receivable' ? 'Tipo: a receber' : 'Tipo: a pagar';
        }

        if (filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $lines[] = 'Situação: somente vencidos';
        } elseif (filled($filters['status'] ?? null)) {
            $status = AccountStatus::tryFrom($filters['status']);

            if ($status !== null) {
                $lines[] = "Status: {$status->label()}";
            }
        }

        foreach ([
            ['Conta bancária', BankAccount::class, $filters['bank_account_id'] ?? null],
            ['Cartão', CreditCard::class, $filters['credit_card_id'] ?? null],
            ['Centro de custo', CostCenter::class, $filters['cost_center_id'] ?? null],
        ] as [$label, $model, $uuid]) {
            $name = $this->resolveName($model, $uuid);

            if ($name !== null) {
                $lines[] = "{$label}: {$name}";
            }
        }

        $dueRange = $this->dateRangeLine('Vencimento', $filters['due_from'] ?? null, $filters['due_to'] ?? null);

        if ($dueRange !== null) {
            $lines[] = $dueRange;
        }

        $paidRange = $this->dateRangeLine('Data da baixa', $filters['paid_from'] ?? null, $filters['paid_to'] ?? null);

        if ($paidRange !== null) {
            $lines[] = $paidRange;
        }

        if ($lines === []) {
            $lines[] = 'Todos os lançamentos';
        }

        return $lines;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function resolveName(string $model, mixed $uuid): ?string
    {
        if (! filled($uuid)) {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('name');
    }

    private function dateRangeLine(string $label, mixed $from, mixed $to): ?string
    {
        $format = fn (mixed $date): string => Carbon::parse($date)->format('d/m/Y');

        if (filled($from) && filled($to)) {
            return "{$label}: {$format($from)} até {$format($to)}";
        }

        if (filled($from)) {
            return "{$label}: a partir de {$format($from)}";
        }

        if (filled($to)) {
            return "{$label}: até {$format($to)}";
        }

        return null;
    }

    private function streamXlsx(string $filename, string $sheetTitle, callable $configure): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $configure($sheet);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function applyTitleBlock(Worksheet $sheet, string $title, array $lines): int
    {
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 2;

        foreach ($lines as $line) {
            $sheet->setCellValue("A{$row}", $line);
            $row++;
        }

        $sheet->getStyle('A2:A'.($row - 1))->getFont()->setColor(new Color('FF4B5563'));

        return $row + 1;
    }

    private function applyHeaderRow(Worksheet $sheet, int $row): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(self::COLUMN_COUNT);

        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFDBEAFE'],
            ],
            'borders' => ['allBorders' => $this->thinBorder()],
        ]);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $this->applyCurrencyColumns($sheet, $row, $row);
    }

    private function applyDataArea(Worksheet $sheet, int $startRow, int $endRow): void
    {
        if ($endRow < $startRow) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(self::COLUMN_COUNT);

        $sheet->getStyle("A{$startRow}:{$lastColumn}{$endRow}")->applyFromArray([
            'borders' => ['allBorders' => $this->thinBorder()],
        ]);
        $this->applyCurrencyColumns($sheet, $startRow, $endRow);
    }

    private function applyTotalsRow(Worksheet $sheet, int $row): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(self::COLUMN_COUNT);

        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF9FAFB'],
            ],
            'borders' => ['allBorders' => $this->thinBorder()],
        ]);
        $this->applyCurrencyColumns($sheet, $row, $row);
    }

    private function applyCurrencyColumns(Worksheet $sheet, int $startRow, int $endRow): void
    {
        $startColumn = Coordinate::stringFromColumnIndex(self::CURRENCY_START_COLUMN);
        $endColumn = Coordinate::stringFromColumnIndex(self::COLUMN_COUNT);
        $range = "{$startColumn}{$startRow}:{$endColumn}{$endRow}";

        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('"R$ "#,##0.00;-"R$ "#,##0.00');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 14,
            'B' => 40,
            'C' => 24,
            'D' => 24,
            'E' => 20,
            'F' => 22,
            'G' => 22,
            'H' => 20,
            'I' => 12,
            'J' => 12,
            'K' => 12,
            'L' => 12,
            'M' => 12,
            'N' => 40,
            'O' => 16,
            'P' => 16,
            'Q' => 16,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function thinBorder(): array
    {
        return [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FFE5E7EB'],
        ];
    }

    private function xlsxMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
