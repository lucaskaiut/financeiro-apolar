<?php

namespace App\Modules\Report\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\CashFlow\Services\CashFlowService;
use App\Modules\Category\Enums\CategoryType;
use App\Modules\CostCenter\Models\CostCenter;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportService
{
    public function __construct(private readonly CashFlowService $cashFlow) {}

    /**
     * Relatório diário (RF023).
     *
     * @return array<string, mixed>
     */
    public function daily(?string $date = null, ?string $costCenterId = null): array
    {
        $date = $date ? Carbon::parse($date) : now();

        $settlements = Settlement::query()
            ->with(['account.costCenter:id,uuid,name', 'account.category:id,uuid,name'])
            ->whereDate('settled_at', $date->toDateString())
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($accountQuery) => $accountQuery->where('cost_center_id', $costCenterId)))
            ->orderBy('settled_at')
            ->get();

        $payments = [];
        $receipts = [];
        $groupsMap = [];
        $totalPaid = 0.0;
        $totalReceived = 0.0;

        foreach ($settlements as $settlement) {
            $account = $settlement->account;

            if ($account === null) {
                continue;
            }

            $costCenter = $this->defaultCostCenterLabel($account->costCenter?->name);

            if (! isset($groupsMap[$costCenter])) {
                $groupsMap[$costCenter] = [
                    'cost_center' => $costCenter,
                    'payments' => [],
                    'receipts' => [],
                    'total_paid' => 0.0,
                    'total_received' => 0.0,
                    'balance' => 0.0,
                ];
            }

            $entry = [
                'description' => $account->description,
                'cost_center' => $account->costCenter?->name,
                'category' => $account->category?->name,
                'value' => (float) $settlement->value,
            ];

            if ($account->type === AccountType::Receivable) {
                $totalReceived += (float) $settlement->value;
                $receipts[] = $entry;
                $groupsMap[$costCenter]['receipts'][] = $entry;
                $groupsMap[$costCenter]['total_received'] += (float) $settlement->value;
            } else {
                $totalPaid += (float) $settlement->value;
                $payments[] = $entry;
                $groupsMap[$costCenter]['payments'][] = $entry;
                $groupsMap[$costCenter]['total_paid'] += (float) $settlement->value;
            }
        }

        $groups = $this->finalizeDailyGroups($groupsMap);

        return [
            'date' => $date->toDateString(),
            'payments' => $payments,
            'receipts' => $receipts,
            'groups' => $groups,
            'total_paid' => round($totalPaid, 2),
            'total_received' => round($totalReceived, 2),
            'balance' => round($totalReceived - $totalPaid, 2),
        ];
    }

    /**
     * Relatório semanal (RF024).
     *
     * @return array<string, mixed>
     */
    public function weekly(?string $from = null, ?string $to = null, ?string $costCenterId = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfWeek();
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfWeek();

        $settlements = Settlement::query()
            ->with(['account.costCenter:id,uuid,name'])
            ->whereBetween('settled_at', [$from, $to])
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($accountQuery) => $accountQuery->where('cost_center_id', $costCenterId)))
            ->get();

        $totalPaid = 0.0;
        $totalReceived = 0.0;
        $groupsMap = [];

        foreach ($settlements as $settlement) {
            $account = $settlement->account;

            if ($account === null) {
                continue;
            }

            $costCenter = $this->defaultCostCenterLabel($account->costCenter?->name);

            if (! isset($groupsMap[$costCenter])) {
                $groupsMap[$costCenter] = [
                    'cost_center' => $costCenter,
                    'total_paid' => 0.0,
                    'total_received' => 0.0,
                    'net_balance' => 0.0,
                ];
            }

            $isReceivable = $account->type === AccountType::Receivable;

            if ($isReceivable) {
                $totalReceived += (float) $settlement->value;
                $groupsMap[$costCenter]['total_received'] += (float) $settlement->value;
            } else {
                $totalPaid += (float) $settlement->value;
                $groupsMap[$costCenter]['total_paid'] += (float) $settlement->value;
            }
        }

        $groups = $this->finalizeWeeklyGroups($groupsMap);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'groups' => $groups,
            'total_paid' => round($totalPaid, 2),
            'total_received' => round($totalReceived, 2),
            'net_balance' => round($totalReceived - $totalPaid, 2),
        ];
    }

    /**
     * Relatório de provisão (RF025) — matriz por centro de custo e dia.
     *
     * @return array<string, mixed>
     */
    public function provision(?string $from = null, ?string $to = null, int $days = 30, ?string $costCenterId = null): array
    {
        [$fromDate, $toDate] = $this->resolveProvisionPeriod($from, $to, $days);
        $rawRows = $this->provisionRawRows($fromDate, $toDate, $costCenterId);

        return $this->buildProvisionMatrix($rawRows, $fromDate, $toDate);
    }

    public function provisionExport(?string $from = null, ?string $to = null, int $days = 30, ?string $costCenterId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->provision($from, $to, $days, $costCenterId);
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);

        $fromLabel = Carbon::parse($data['from'])->format('d/m/Y');
        $toLabel = Carbon::parse($data['to'])->format('d/m/Y');
        $columnKeys = array_column($data['columns'], 'key');
        $columnLabels = array_column($data['columns'], 'label');

        return $this->streamXlsx('relatorio-provisao.xlsx', 'Provisão', function (Worksheet $sheet) use ($data, $fromLabel, $toLabel, $costCenterLabel, $columnKeys, $columnLabels): void {
            $headerRow = $this->applyXlsxTitleBlock(
                $sheet,
                'Relatório de provisão',
                [
                    "Período: {$fromLabel} até {$toLabel}",
                    "Centro de custo: {$costCenterLabel}",
                ],
                sprintf(
                    'Total a receber: R$ %s | Total a pagar: R$ %s | Saldo líquido: R$ %s',
                    number_format((float) $data['total_in'], 2, ',', '.'),
                    number_format((float) $data['total_out'], 2, ',', '.'),
                    number_format((float) $data['grand_total']['total'], 2, ',', '.'),
                ),
            );

            $columnCount = count($columnLabels) + 2;
            $headers = array_merge(['Conta'], $columnLabels, ['Total']);
            $row = $headerRow + 1;

            foreach ($data['groups'] as $group) {
                $sheet->setCellValue("A{$row}", $group['cost_center']);
                $this->applyXlsxSectionBanner($sheet, $row, $columnCount);
                $row++;

                $groupHeaderRow = $row;
                $sheet->fromArray($headers, null, "A{$row}");
                $this->applyXlsxColumnHeader($sheet, $groupHeaderRow, $columnCount, 'FFF3F4F6');
                $row++;

                $dataStartRow = $row;

                foreach ($group['rows'] as $accountRow) {
                    $cells = [$accountRow['description']];

                    foreach ($columnKeys as $key) {
                        $cells[] = $this->xlsxMoney($accountRow['amounts'][$key] ?? null);
                    }

                    $cells[] = null;
                    $sheet->fromArray($cells, null, "A{$row}");
                    $row++;
                }

                $subtotalCells = ['Subtotal'];

                foreach ($columnKeys as $key) {
                    $subtotalCells[] = $this->xlsxMoney($group['subtotal']['amounts'][$key] ?? null);
                }

                $subtotalCells[] = $this->xlsxMoney($group['subtotal']['total']);
                $sheet->fromArray($subtotalCells, null, "A{$row}");
                $this->applyXlsxSubtotalRow($sheet, $row, $columnCount);
                $this->applyXlsxDataArea($sheet, $dataStartRow, $row - 1, $columnCount, 1, false);
                $row += 2;
            }

            $sheet->setCellValue("A{$row}", 'TOTAL GERAL');
            $this->applyXlsxSectionBanner($sheet, $row, $columnCount);
            $row++;

            $grandHeaderRow = $row;
            $sheet->fromArray($headers, null, "A{$row}");
            $this->applyXlsxColumnHeader($sheet, $grandHeaderRow, $columnCount, 'FFF3F4F6');
            $row++;

            $grandCells = ['Total geral'];

            foreach ($columnKeys as $key) {
                $grandCells[] = $this->xlsxMoney($data['grand_total']['amounts'][$key] ?? null);
            }

            $grandCells[] = $this->xlsxMoney($data['grand_total']['total']);
            $grandRow = $row;
            $sheet->fromArray($grandCells, null, "A{$row}");
            $this->applyXlsxTotalRow($sheet, $grandRow, $columnCount, true);

            $this->applyXlsxColumnWidths($sheet, $columnCount, 36, 14);
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveProvisionPeriod(?string $from, ?string $to, int $days): array
    {
        if ($from !== null || $to !== null) {
            $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay();
            $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->addDays(min(max($days, 1), 365))->endOfDay();
        } else {
            $days = min(max($days, 1), 365);
            $fromDate = now()->startOfDay();
            $toDate = now()->addDays($days)->endOfDay();
        }

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        $periodDays = (int) $fromDate->copy()->startOfDay()->diffInDays($toDate->copy()->startOfDay());

        if ($periodDays > 365) {
            $toDate = $fromDate->copy()->addDays(365)->endOfDay();
        }

        return [$fromDate, $toDate];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function provisionRawRows(Carbon $fromDate, Carbon $toDate, ?string $costCenterId): array
    {
        $base = fn () => FinancialAccount::query()
            ->with(['costCenter:id,uuid,name'])
            ->withSum('settlements', 'value')
            ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
            ->whereDate('due_date', '>=', $fromDate->toDateString())
            ->whereDate('due_date', '<=', $toDate->toDateString())
            ->when($costCenterId, fn ($q) => $q->where('cost_center_id', $costCenterId));

        $futureAccounts = $base()->whereNull('recurrence_id')->whereNull('transfer_id')->whereNull('installment_group_id')->get();
        $installments = $base()->whereNotNull('installment_group_id')->get();
        $recurrences = $base()->whereNotNull('recurrence_id')->get();

        $rows = [];

        foreach ($futureAccounts->concat($installments)->concat($recurrences) as $account) {
            $remaining = round((float) $account->value - (float) ($account->settlements_sum_value ?? 0), 2);

            if ($remaining <= 0) {
                continue;
            }

            $signedAmount = $account->type === AccountType::Receivable
                ? $remaining
                : round(-$remaining, 2);

            $rows[] = [
                'cost_center_id' => $account->cost_center_id,
                'cost_center_name' => $this->defaultCostCenterLabel($account->costCenter?->name),
                'account_id' => $account->uuid,
                'account_description' => $account->description,
                'due_date' => $account->due_date->toDateString(),
                'amount' => $signedAmount,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $dateCompare = strcmp($a['due_date'], $b['due_date']);

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcmp($a['account_description'], $b['account_description']);
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rawRows
     * @return array<string, mixed>
     */
    private function buildProvisionMatrix(array $rawRows, Carbon $fromDate, Carbon $toDate): array
    {
        $periodDays = (int) $fromDate->copy()->startOfDay()->diffInDays($toDate->copy()->startOfDay());
        $columns = [];
        $dayKeys = [];

        for ($i = 0; $i <= $periodDays; $i++) {
            $date = $fromDate->copy()->addDays($i);
            $key = $date->toDateString();
            $dayKeys[] = $key;
            $columns[] = [
                'key' => $key,
                'label' => $date->format('d/m'),
            ];
        }

        $groupsMap = [];

        foreach ($rawRows as $row) {
            $costCenter = $row['cost_center_name'];

            if (! isset($groupsMap[$costCenter])) {
                $groupsMap[$costCenter] = [
                    'cost_center_id' => $row['cost_center_id'],
                    'cost_center' => $costCenter,
                    'rows' => [],
                    'subtotal' => [
                        'amounts' => array_fill_keys($dayKeys, 0.0),
                        'total' => 0.0,
                    ],
                ];
            }

            $amounts = array_fill_keys($dayKeys, null);
            $dueDate = $row['due_date'];

            if (array_key_exists($dueDate, $amounts)) {
                $amounts[$dueDate] = $row['amount'];
                $groupsMap[$costCenter]['subtotal']['amounts'][$dueDate] += $row['amount'];
                $groupsMap[$costCenter]['subtotal']['total'] += $row['amount'];
            }

            $groupsMap[$costCenter]['rows'][] = [
                'account_id' => $row['account_id'],
                'description' => $row['account_description'],
                'due_date' => $dueDate,
                'amounts' => $amounts,
                'total' => $row['amount'],
            ];
        }

        $groups = array_values($groupsMap);
        usort($groups, fn (array $a, array $b): int => strcmp($a['cost_center'], $b['cost_center']));

        foreach ($groups as &$group) {
            usort($group['rows'], function (array $a, array $b): int {
                $dateCompare = strcmp($a['due_date'], $b['due_date']);

                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return strcmp($a['description'], $b['description']);
            });

            foreach ($group['subtotal']['amounts'] as $key => $value) {
                $group['subtotal']['amounts'][$key] = $value == 0.0 ? null : round($value, 2);
            }

            $group['subtotal']['total'] = round($group['subtotal']['total'], 2);
        }

        unset($group);

        $grandAmounts = array_fill_keys($dayKeys, 0.0);
        $grandTotal = 0.0;

        foreach ($rawRows as $row) {
            if (array_key_exists($row['due_date'], $grandAmounts)) {
                $grandAmounts[$row['due_date']] += $row['amount'];
                $grandTotal += $row['amount'];
            }
        }

        foreach ($grandAmounts as $key => $value) {
            $grandAmounts[$key] = $value == 0.0 ? null : round($value, 2);
        }

        $totalIn = round(array_sum(array_map(
            fn (array $row): float => $row['amount'] > 0 ? $row['amount'] : 0.0,
            $rawRows,
        )), 2);

        $totalOut = round(abs(array_sum(array_map(
            fn (array $row): float => $row['amount'] < 0 ? $row['amount'] : 0.0,
            $rawRows,
        ))), 2);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'columns' => $columns,
            'rows' => $rawRows,
            'groups' => $groups,
            'grand_total' => [
                'amounts' => $grandAmounts,
                'total' => round($grandTotal, 2),
            ],
            'total_in' => $totalIn,
            'total_out' => $totalOut,
        ];
    }

    /**
     * Relatório por categoria (RF026).
     *
     * @return array<string, mixed>
     */
    public function byCategory(?string $from = null, ?string $to = null, ?string $costCenterId = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth();

        $settlements = Settlement::query()
            ->with([
                'account.category:id,uuid,name,type',
                'account.subcategory:id,uuid,name',
                'account.costCenter:id,uuid,name',
            ])
            ->whereBetween('settled_at', [$from, $to])
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($accountQuery) => $accountQuery->where('cost_center_id', $costCenterId)))
            ->get();

        $expense = [];
        $groupsMap = [];

        foreach ($settlements as $settlement) {
            $account = $settlement->account;
            $category = $account?->category;

            if ($category === null || $account === null || $category->type === AccountType::Receivable) {
                continue;
            }

            $costCenter = $this->defaultCostCenterLabel($account->costCenter?->name);
            $key = $category->name;

            if (! isset($expense[$key])) {
                $expense[$key] = 0.0;
            }

            $expense[$key] = round($expense[$key] + (float) $settlement->value, 2);

            if (! isset($groupsMap[$costCenter])) {
                $groupsMap[$costCenter] = [
                    'cost_center' => $costCenter,
                    'expense' => [],
                    'total_expense' => 0.0,
                ];
            }

            if (! isset($groupsMap[$costCenter]['expense'][$key])) {
                $groupsMap[$costCenter]['expense'][$key] = 0.0;
            }

            $groupsMap[$costCenter]['expense'][$key] = round($groupsMap[$costCenter]['expense'][$key] + (float) $settlement->value, 2);
        }

        $groups = $this->finalizeCategoryGroups($groupsMap);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'expense' => $this->asList($expense),
            'groups' => $groups,
            'matrix' => $this->buildCategoryMatrix($settlements, $from, $to),
        ];
    }

    /**
     * Matriz pivot do relatório por categoria: centro de custo → categoria → subcategoria × mês.
     *
     * @param  \Illuminate\Support\Collection<int, Settlement>  $settlements
     * @return array<string, mixed>
     */
    private function buildCategoryMatrix($settlements, Carbon $from, Carbon $to): array
    {
        [$columns, $monthKeys] = $this->buildMonthColumns($from, $to);
        $groupsMap = [];

        foreach ($settlements as $settlement) {
            $account = $settlement->account;
            $category = $account?->category;

            if ($category === null || $account === null || $category->type !== CategoryType::Expense) {
                continue;
            }

            $monthKey = Carbon::parse($settlement->settled_at)->format('Y-m');

            if (! in_array($monthKey, $monthKeys, true)) {
                continue;
            }

            $costCenter = $this->defaultCostCenterLabel($account->costCenter?->name);
            $categoryName = $category->name;
            $amount = round((float) $settlement->value, 2);
            $accountKey = $account->uuid;

            if (! isset($groupsMap[$costCenter])) {
                $groupsMap[$costCenter] = [
                    'cost_center' => $costCenter,
                    'categories' => [],
                    'subtotal' => [
                        'amounts' => array_fill_keys($monthKeys, 0.0),
                        'total' => 0.0,
                    ],
                ];
            }

            if (! isset($groupsMap[$costCenter]['categories'][$categoryName])) {
                $groupsMap[$costCenter]['categories'][$categoryName] = [
                    'category' => $categoryName,
                    'direct_rows' => [],
                    'subcategories' => [],
                    'subtotal' => [
                        'amounts' => array_fill_keys($monthKeys, 0.0),
                        'total' => 0.0,
                    ],
                ];
            }

            if ($account->subcategory === null) {
                $rowBucket = &$groupsMap[$costCenter]['categories'][$categoryName]['direct_rows'];
            } else {
                $subcategoryName = $account->subcategory->name;

                if (! isset($groupsMap[$costCenter]['categories'][$categoryName]['subcategories'][$subcategoryName])) {
                    $groupsMap[$costCenter]['categories'][$categoryName]['subcategories'][$subcategoryName] = [
                        'subcategory' => $subcategoryName,
                        'rows' => [],
                        'subtotal' => [
                            'amounts' => array_fill_keys($monthKeys, 0.0),
                            'total' => 0.0,
                        ],
                    ];
                }

                $rowBucket = &$groupsMap[$costCenter]['categories'][$categoryName]['subcategories'][$subcategoryName]['rows'];
            }

            if (! isset($rowBucket[$accountKey])) {
                $rowBucket[$accountKey] = [
                    'label' => $account->description,
                    'amounts' => array_fill_keys($monthKeys, 0.0),
                    'total' => 0.0,
                ];
            }

            $rowBucket[$accountKey]['amounts'][$monthKey] += $amount;
            $rowBucket[$accountKey]['total'] += $amount;
            $groupsMap[$costCenter]['categories'][$categoryName]['subtotal']['amounts'][$monthKey] += $amount;
            $groupsMap[$costCenter]['categories'][$categoryName]['subtotal']['total'] += $amount;

            if ($account->subcategory !== null) {
                $subcategoryName = $account->subcategory->name;
                $groupsMap[$costCenter]['categories'][$categoryName]['subcategories'][$subcategoryName]['subtotal']['amounts'][$monthKey] += $amount;
                $groupsMap[$costCenter]['categories'][$categoryName]['subcategories'][$subcategoryName]['subtotal']['total'] += $amount;
            }

            $groupsMap[$costCenter]['subtotal']['amounts'][$monthKey] += $amount;
            $groupsMap[$costCenter]['subtotal']['total'] += $amount;
        }

        $groups = [];

        foreach ($groupsMap as $group) {
            $categories = array_values($group['categories']);

            usort($categories, fn (array $a, array $b): int => $b['subtotal']['total'] <=> $a['subtotal']['total']);

            foreach ($categories as &$category) {
                $category['direct_rows'] = $this->finalizeCategoryMatrixRows($category['direct_rows']);

                $subcategories = array_values($category['subcategories']);
                usort($subcategories, fn (array $a, array $b): int => $b['subtotal']['total'] <=> $a['subtotal']['total']);

                foreach ($subcategories as &$subcategory) {
                    $subcategory['rows'] = $this->finalizeCategoryMatrixRows($subcategory['rows']);
                    $subcategory['subtotal']['amounts'] = $this->normalizeMatrixAmounts($subcategory['subtotal']['amounts']);
                    $subcategory['subtotal']['total'] = round($subcategory['subtotal']['total'], 2);
                }

                unset($subcategory);

                $category['subcategories'] = $subcategories;
                $category['subtotal']['amounts'] = $this->normalizeMatrixAmounts($category['subtotal']['amounts']);
                $category['subtotal']['total'] = round($category['subtotal']['total'], 2);
            }

            unset($category);

            $group['categories'] = $categories;
            $group['subtotal']['amounts'] = $this->normalizeMatrixAmounts($group['subtotal']['amounts']);
            $group['subtotal']['total'] = round($group['subtotal']['total'], 2);
            $groups[] = $group;
        }

        usort($groups, fn (array $a, array $b): int => strcmp($a['cost_center'], $b['cost_center']));

        $grandAmounts = array_fill_keys($monthKeys, 0.0);
        $grandTotal = 0.0;

        foreach ($groupsMap as $group) {
            foreach ($monthKeys as $key) {
                $grandAmounts[$key] += $group['subtotal']['amounts'][$key];
            }

            $grandTotal += $group['subtotal']['total'];
        }

        return [
            'columns' => $columns,
            'groups' => $groups,
            'grand_total' => [
                'amounts' => $this->normalizeMatrixAmounts($grandAmounts),
                'total' => round($grandTotal, 2),
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsMap
     * @return list<array<string, mixed>>
     */
    private function finalizeCategoryMatrixRows(array $rowsMap): array
    {
        $rows = array_values($rowsMap);

        usort($rows, fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['label'], $b['label']));

        foreach ($rows as &$row) {
            $row['amounts'] = $this->normalizeMatrixAmounts($row['amounts']);
            $row['total'] = round($row['total'], 2);
        }

        unset($row);

        return $rows;
    }

    /**
     * @return array{0: list<array{key: string, label: string}>, 1: list<string>}
     */
    private function buildMonthColumns(Carbon $from, Carbon $to): array
    {
        $columns = [];
        $monthKeys = [];
        $current = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();

        while ($current->lte($end)) {
            $key = $current->format('Y-m');
            $monthKeys[] = $key;
            $columns[] = [
                'key' => $key,
                'label' => $this->monthAbbreviation($current),
            ];
            $current->addMonth();
        }

        return [$columns, $monthKeys];
    }

    private function monthAbbreviation(Carbon $date): string
    {
        return match ((int) $date->month) {
            1 => 'JAN',
            2 => 'FEV',
            3 => 'MAR',
            4 => 'ABR',
            5 => 'MAI',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AGO',
            9 => 'SET',
            10 => 'OUT',
            11 => 'NOV',
            12 => 'DEZ',
        };
    }

    /**
     * @param  array<string, float>  $amounts
     * @return array<string, float|null>
     */
    private function normalizeMatrixAmounts(array $amounts): array
    {
        foreach ($amounts as $key => $value) {
            $amounts[$key] = $value == 0.0 ? null : round($value, 2);
        }

        return $amounts;
    }

    /**
     * Resumo mensal por centro de custo (despesas liquidadas, colunas por mês).
     *
     * @return array<string, mixed>
     */
    public function monthlySummary(?string $from = null, ?string $to = null, ?string $costCenterId = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth();

        [$columns, $monthKeys] = $this->buildMonthColumns($from, $to);

        $rowsMap = [];

        foreach (CostCenter::query()
            ->when($costCenterId, fn ($q) => $q->where('uuid', $costCenterId))
            ->orderBy('name')
            ->get() as $costCenter) {
            $rowsMap[$costCenter->uuid] = [
                'cost_center_id' => $costCenter->uuid,
                'cost_center' => $costCenter->name,
                'amounts' => array_fill_keys($monthKeys, 0.0),
                'total' => 0.0,
            ];
        }

        $settlements = Settlement::query()
            ->with(['account.category:id,uuid,name,type', 'account.costCenter:id,uuid,name'])
            ->whereBetween('settled_at', [$from, $to])
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($accountQuery) => $accountQuery->where('cost_center_id', $costCenterId)))
            ->get();

        foreach ($settlements as $settlement) {
            $account = $settlement->account;
            $category = $account?->category;

            if ($category === null || $account === null || $category->type !== CategoryType::Expense) {
                continue;
            }

            $monthKey = Carbon::parse($settlement->settled_at)->format('Y-m');

            if (! in_array($monthKey, $monthKeys, true)) {
                continue;
            }

            $rowKey = $account->cost_center_id ?? '__none__';

            if (! isset($rowsMap[$rowKey])) {
                $rowsMap[$rowKey] = [
                    'cost_center_id' => $account->cost_center_id,
                    'cost_center' => $this->defaultCostCenterLabel($account->costCenter?->name),
                    'amounts' => array_fill_keys($monthKeys, 0.0),
                    'total' => 0.0,
                ];
            }

            $amount = round((float) $settlement->value, 2);
            $rowsMap[$rowKey]['amounts'][$monthKey] += $amount;
            $rowsMap[$rowKey]['total'] += $amount;
        }

        $rows = array_values($rowsMap);
        usort($rows, fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['cost_center'], $b['cost_center']));

        $grandAmounts = array_fill_keys($monthKeys, 0.0);
        $grandTotal = 0.0;

        foreach ($rows as &$row) {
            foreach ($monthKeys as $key) {
                $grandAmounts[$key] += $row['amounts'][$key];
            }

            $row['amounts'] = $this->normalizeMatrixAmounts($row['amounts']);
            $row['total'] = round($row['total'], 2);
            $grandTotal += $row['total'];
        }

        unset($row);

        $monthCount = max(count($monthKeys), 1);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'columns' => $columns,
            'rows' => $rows,
            'grand_total' => [
                'amounts' => $this->normalizeMatrixAmounts($grandAmounts),
                'total' => round($grandTotal, 2),
            ],
            'monthly_average' => round($grandTotal / $monthCount, 2),
        ];
    }

    /**
     * Relatório por centro de custo (RF027).
     *
     * @return array<string, mixed>
     */
    public function byCostCenter(?string $costCenterId = null): array
    {
        $costCenters = CostCenter::query()
            ->when($costCenterId, fn ($q) => $q->where('uuid', $costCenterId))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($costCenters as $costCenter) {
            $income = (float) Settlement::query()
                ->whereHas('account', fn ($q) => $q->where('cost_center_id', $costCenter->uuid)->where('type', AccountType::Receivable->value))
                ->sum('value');

            $expense = (float) Settlement::query()
                ->whereHas('account', fn ($q) => $q->where('cost_center_id', $costCenter->uuid)->where('type', AccountType::Payable->value))
                ->sum('value');

            $rows[] = [
                'cost_center_id' => $costCenter->uuid,
                'cost_center' => $costCenter->name,
                'initial_balance' => (float) $costCenter->initial_balance,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'balance' => round((float) $costCenter->initial_balance + $income - $expense, 2),
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Demonstrativo de fluxo de caixa (RF028): realizado, projetado e comparativo.
     *
     * @return array<string, mixed>
     */
    public function cashFlow(?string $from = null, ?string $to = null, int $days = 30, ?string $costCenterId = null): array
    {
        $realized = $this->cashFlow->realized($from, $to, $costCenterId, null);
        $projected = $this->cashFlow->projected(null, null, $days, $costCenterId);

        $groups = [];

        if ($costCenterId) {
            $groups[] = [
                'cost_center' => $this->resolveCostCenterLabel($costCenterId),
                'realized_net' => round($realized['total_in'] - $realized['total_out'], 2),
                'projected_net' => round($projected['total_in'] - $projected['total_out'], 2),
                'expected_final_balance' => round($realized['final_balance'] + $projected['total_in'] - $projected['total_out'], 2),
                'total_in' => round($realized['total_in'] + $projected['total_in'], 2),
                'total_out' => round($realized['total_out'] + $projected['total_out'], 2),
            ];
        } else {
            $groups = $this->cashFlowGroups($from, $to, $days);
        }

        return [
            'realized' => [
                'from' => $realized['from'],
                'to' => $realized['to'],
                'opening_balance' => $realized['opening_balance'],
                'total_in' => $realized['total_in'],
                'total_out' => $realized['total_out'],
                'final_balance' => $realized['final_balance'],
            ],
            'projected' => [
                'days' => $projected['days'],
                'opening_balance' => $projected['opening_balance'],
                'total_in' => $projected['total_in'],
                'total_out' => $projected['total_out'],
            ],
            'comparative' => [
                'realized_net' => round($realized['total_in'] - $realized['total_out'], 2),
                'projected_net' => round($projected['total_in'] - $projected['total_out'], 2),
                'expected_final_balance' => round($realized['final_balance'] + $projected['total_in'] - $projected['total_out'], 2),
            ],
            'groups' => $groups,
        ];
    }

    /**
     * Relatório de contas a pagar por centro de custo (RF029): contas em aberto
     * (vencidas, do dia e futuras) para planejamento de pagamentos.
     *
     * @return array<string, mixed>
     */
    public function payables(?string $from = null, ?string $to = null, ?string $costCenterId = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $today = now()->startOfDay();
        $todayString = $today->toDateString();

        $accounts = FinancialAccount::query()
            ->with(['costCenter:id,uuid,name', 'category:id,uuid,name'])
            ->withSum('settlements', 'value')
            ->where('type', AccountType::Payable)
            ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
            ->when($costCenterId, fn ($q) => $q->where('cost_center_id', $costCenterId))
            ->whereDate('due_date', '<=', $to->toDateString())
            ->when($from, fn ($q) => $q->where(function ($inner) use ($from, $today): void {
                $inner->whereDate('due_date', '>=', $from->toDateString())
                    ->orWhereDate('due_date', '<', $today->toDateString());
            }))
            ->orderBy('due_date')
            ->orderBy('description')
            ->get();

        $rows = [];
        $totalOpen = 0.0;
        $totalOverdue = 0.0;

        foreach ($accounts as $account) {
            $remaining = round((float) $account->value - (float) ($account->settlements_sum_value ?? 0), 2);

            if ($remaining <= 0) {
                continue;
            }

            $isOverdue = $account->due_date->lt($today);
            $isDueToday = $account->due_date->toDateString() === $todayString;

            $rows[] = [
                'id' => $account->uuid,
                'description' => $account->description,
                'counterparty' => $account->counterparty,
                'cost_center_id' => $account->cost_center_id,
                'cost_center' => $account->costCenter?->name,
                'category' => $account->category?->name,
                'value' => (float) $account->value,
                'remaining_amount' => $remaining,
                'due_date' => $account->due_date->toDateString(),
                'installment' => $account->installment_total > 1 ? "{$account->installment_number}/{$account->installment_total}" : null,
                'status' => $account->status->value,
                'is_overdue' => $isOverdue,
                'is_due_today' => $isDueToday,
            ];

            $totalOpen += $remaining;

            if ($isOverdue || $isDueToday) {
                $totalOverdue += $remaining;
            }
        }

        return [
            'reference_date' => $todayString,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'cost_center_id' => $costCenterId,
            'cost_center' => $costCenterId ? CostCenter::query()->where('uuid', $costCenterId)->value('name') : null,
            'accounts' => $rows,
            'groups' => $this->payablesListingGroups($rows),
            'total_open' => round($totalOpen, 2),
            'total_overdue' => round($totalOverdue, 2),
            'count' => count($rows),
        ];
    }

    /**
     * @param  list<string>  $selectedIds
     * @return array<string, mixed>
     */
    public function payablesExport(?string $from = null, ?string $to = null, ?string $costCenterId = null, array $selectedIds = []): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->payables($from, $to, $costCenterId);
        $exportGroups = $this->buildPayablesExportGroups($data['accounts'], $selectedIds, $data['reference_date']);
        $summary = $this->buildPayablesSummary($exportGroups, $data['reference_date']);
        $fromLabel = Carbon::parse($data['from'])->format('d/m/Y');
        $toLabel = Carbon::parse($data['to'])->format('d/m/Y');
        $referenceLabel = Carbon::parse($data['reference_date'])->format('d/m/Y');
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);

        return $this->streamXlsx('contas-a-pagar.xlsx', 'Contas a pagar', function (Worksheet $sheet) use ($exportGroups, $summary, $fromLabel, $toLabel, $referenceLabel, $costCenterLabel): void {
            $row = $this->applyXlsxTitleBlock(
                $sheet,
                'Relatório de contas a pagar',
                [
                    "Período: {$fromLabel} até {$toLabel}",
                    "Centro de custo: {$costCenterLabel}",
                    "Referência: {$referenceLabel}",
                ],
            );
            $columnCount = 3;

            foreach ($exportGroups as $group) {
                $sheet->setCellValue("A{$row}", $group['cost_center']);
                $this->applyXlsxSectionBanner($sheet, $row, $columnCount);
                $row++;

                if (count($group['overdue']['accounts']) > 0) {
                    $sheet->setCellValue("A{$row}", 'EM ATRASO');
                    $sheet->mergeCells("A{$row}:{$this->xlsxColumnLetter($columnCount)}{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $row++;

                    $headerRow = $row;
                    $sheet->fromArray(['Data', 'Descrição', 'Valor'], null, "A{$row}");
                    $row++;
                    $dataStartRow = $row;

                    foreach ($group['overdue']['accounts'] as $account) {
                        $sheet->fromArray([
                            Carbon::parse($account['due_date'])->format('d/m/Y'),
                            $account['description'],
                            $this->xlsxMoney($account['remaining_amount']),
                        ], null, "A{$row}");
                        $row++;
                    }

                    $this->applyXlsxColumnHeader($sheet, $headerRow, $columnCount, 'FFF3F4F6');
                    $this->applyXlsxDataArea($sheet, $dataStartRow, $row - 1, $columnCount, 2, false);

                    $sheet->setCellValue("A{$row}", 'TOTAL EM ATRASO');
                    $sheet->setCellValue('C'.$row, $this->xlsxMoney($group['overdue']['total']));
                    $this->applyXlsxFooterRow($sheet, $row, $columnCount);
                    $sheet->getStyle("A{$row}:{$this->xlsxColumnLetter($columnCount)}{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    $row += 2;
                }

                if (count($group['due_today']['accounts']) > 0) {
                    $sheet->setCellValue("A{$row}", "PAGOS EM {$referenceLabel}");
                    $sheet->mergeCells("A{$row}:{$this->xlsxColumnLetter($columnCount)}{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setARGB('FF008000');
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $row++;

                    $headerRow = $row;
                    $sheet->fromArray(['Data', 'Descrição', 'Valor'], null, "A{$row}");
                    $row++;
                    $dataStartRow = $row;

                    foreach ($group['due_today']['accounts'] as $account) {
                        $sheet->fromArray([
                            Carbon::parse($account['due_date'])->format('d/m/Y'),
                            $account['description'],
                            $this->xlsxMoney($account['remaining_amount']),
                        ], null, "A{$row}");
                        $row++;
                    }

                    $this->applyXlsxColumnHeader($sheet, $headerRow, $columnCount, 'FFF3F4F6');
                    $this->applyXlsxDataArea($sheet, $dataStartRow, $row - 1, $columnCount, 2, false);

                    $sheet->setCellValue("A{$row}", 'TOTAL PAGO');
                    $sheet->setCellValue('C'.$row, $this->xlsxMoney($group['due_today']['total']));
                    $this->applyXlsxFooterRow($sheet, $row, $columnCount);
                    $sheet->getStyle("A{$row}:{$this->xlsxColumnLetter($columnCount)}{$row}")->getFont()->getColor()->setARGB('FF008000');
                    $row += 2;
                }

                $row++;
            }

            $sheet->setCellValue("A{$row}", "RESUMO GERAL EM {$referenceLabel}");
            $sheet->mergeCells("A{$row}:{$this->xlsxColumnLetter($columnCount)}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row += 2;

            foreach ([
                ['data' => $summary['paid_today'], 'totalLabel' => 'TOTAL PAGOS', 'color' => 'FF008000'],
                ['data' => $summary['overdue'], 'totalLabel' => 'TOTAL EM ATRASO', 'color' => 'FFFF0000'],
            ] as $summaryBlock) {
                $sheet->setCellValue("A{$row}", $summaryBlock['data']['title']);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setARGB($summaryBlock['color']);
                $row++;

                $headerRow = $row;
                $sheet->fromArray(['Centro de custo', 'Valor'], null, "A{$row}");
                $row++;
                $dataStartRow = $row;

                foreach ($summaryBlock['data']['rows'] as $summaryRow) {
                    $sheet->fromArray([
                        $summaryRow['cost_center'],
                        $this->xlsxMoney($summaryRow['amount']),
                    ], null, "A{$row}");
                    $row++;
                }

                $this->applyXlsxStandardTable(
                    $sheet,
                    headerRow: $headerRow,
                    dataStartRow: $dataStartRow,
                    dataEndRow: max($dataStartRow, $row - 1),
                    totalRow: null,
                    columnCount: 2,
                    labelColumns: 1,
                    labelBold: false,
                );

                $sheet->setCellValue("A{$row}", $summaryBlock['totalLabel']);
                $sheet->setCellValue('B'.$row, $this->xlsxMoney($summaryBlock['data']['total']));
                $this->applyXlsxFooterRow($sheet, $row, 2);
                $sheet->getStyle("A{$row}:B{$row}")->getFont()->getColor()->setARGB($summaryBlock['color']);
                $row += 2;
            }

            $this->applyXlsxColumnWidths($sheet, $columnCount, 36, 18);
        });
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function isPayablesReportOverdue(array $account, bool $selected): bool
    {
        return ! $selected && (($account['is_overdue'] ?? false) || ($account['is_due_today'] ?? false));
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @return list<array<string, mixed>>
     */
    private function payablesListingGroups(array $accounts): array
    {
        $groupsMap = [];

        foreach ($accounts as $account) {
            $costCenter = $this->defaultCostCenterLabel($account['cost_center'] ?? null);

            if (! isset($groupsMap[$costCenter])) {
                $groupsMap[$costCenter] = [
                    'cost_center' => $costCenter,
                    'cost_center_id' => $account['cost_center_id'] ?? null,
                    'accounts' => [],
                    'total_open' => 0.0,
                    'total_overdue' => 0.0,
                ];
            }

            $groupsMap[$costCenter]['accounts'][] = $account;
            $groupsMap[$costCenter]['total_open'] += (float) $account['remaining_amount'];

            if (($account['is_overdue'] ?? false) || ($account['is_due_today'] ?? false)) {
                $groupsMap[$costCenter]['total_overdue'] += (float) $account['remaining_amount'];
            }
        }

        $groups = [];

        foreach ($groupsMap as $group) {
            $group['total_open'] = round((float) $group['total_open'], 2);
            $group['total_overdue'] = round((float) $group['total_overdue'], 2);
            $groups[] = $group;
        }

        usort($groups, fn (array $a, array $b): int => strcmp($a['cost_center'], $b['cost_center']));

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @param  list<string>  $selectedIds
     * @return list<array<string, mixed>>
     */
    private function buildPayablesExportGroups(array $accounts, array $selectedIds, string $referenceDate): array
    {
        $selectedSet = array_flip($selectedIds);
        $groupsMap = [];

        foreach ($accounts as $account) {
            $costCenter = $this->defaultCostCenterLabel($account['cost_center'] ?? null);

            if (! isset($groupsMap[$costCenter])) {
                $groupsMap[$costCenter] = [
                    'cost_center' => $costCenter,
                    'cost_center_id' => $account['cost_center_id'] ?? null,
                    'overdue' => [
                        'accounts' => [],
                        'total' => 0.0,
                    ],
                    'due_today' => [
                        'accounts' => [],
                        'total' => 0.0,
                    ],
                    'total_overdue' => 0.0,
                    'total_paid_today' => 0.0,
                ];
            }

            if ($this->isPayablesReportOverdue($account, isset($selectedSet[$account['id']]))) {
                $groupsMap[$costCenter]['overdue']['accounts'][] = $account;
                $groupsMap[$costCenter]['overdue']['total'] += (float) $account['remaining_amount'];
                $groupsMap[$costCenter]['total_overdue'] += (float) $account['remaining_amount'];
            }

            if (isset($selectedSet[$account['id']])) {
                $groupsMap[$costCenter]['due_today']['accounts'][] = $account;
                $groupsMap[$costCenter]['due_today']['total'] += (float) $account['remaining_amount'];
                $groupsMap[$costCenter]['total_paid_today'] += (float) $account['remaining_amount'];
            }
        }

        $groups = [];

        foreach ($groupsMap as $group) {
            if (count($group['overdue']['accounts']) === 0 && count($group['due_today']['accounts']) === 0) {
                continue;
            }

            usort($group['overdue']['accounts'], fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']) ?: strcmp($a['description'], $b['description']));
            usort($group['due_today']['accounts'], fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']) ?: strcmp($a['description'], $b['description']));

            $group['overdue']['total'] = round((float) $group['overdue']['total'], 2);
            $group['due_today']['total'] = round((float) $group['due_today']['total'], 2);
            $group['total_overdue'] = round((float) $group['total_overdue'], 2);
            $group['total_paid_today'] = round((float) $group['total_paid_today'], 2);
            $groups[] = $group;
        }

        usort($groups, fn (array $a, array $b): int => strcmp($a['cost_center'], $b['cost_center']));

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, mixed>
     */
    private function buildPayablesSummary(array $groups, string $referenceDate): array
    {
        $paidRows = [];
        $overdueRows = [];
        $totalPaid = 0.0;
        $totalOverdue = 0.0;

        foreach ($groups as $group) {
            $paidRows[] = [
                'cost_center' => $group['cost_center'],
                'amount' => $group['total_paid_today'],
            ];
            $overdueRows[] = [
                'cost_center' => $group['cost_center'],
                'amount' => $group['total_overdue'],
            ];
            $totalPaid += (float) $group['total_paid_today'];
            $totalOverdue += (float) $group['total_overdue'];
        }

        return [
            'reference_date' => $referenceDate,
            'paid_today' => [
                'title' => 'PAGOS',
                'rows' => $paidRows,
                'total' => round($totalPaid, 2),
            ],
            'overdue' => [
                'title' => 'EM ATRASO',
                'rows' => $overdueRows,
                'total' => round($totalOverdue, 2),
            ],
        ];
    }

    private function defaultCostCenterLabel(?string $name): string
    {
        return $name !== null && $name !== '' ? $name : 'Sem centro de custo';
    }

    /**
     * @param  array<string, array<string, mixed>>  $groupsMap
     * @return list<array<string, mixed>>
     */
    private function finalizeDailyGroups(array $groupsMap): array
    {
        $groups = [];

        foreach ($groupsMap as $group) {
            $group['total_paid'] = round((float) $group['total_paid'], 2);
            $group['total_received'] = round((float) $group['total_received'], 2);
            $group['balance'] = round($group['total_received'] - $group['total_paid'], 2);
            $groups[] = $group;
        }

        usort($groups, fn ($a, $b) => strcmp($a['cost_center'], $b['cost_center']));

        return $groups;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groupsMap
     * @return list<array<string, mixed>>
     */
    private function finalizeWeeklyGroups(array $groupsMap): array
    {
        $groups = [];

        foreach ($groupsMap as $group) {
            $group['total_paid'] = round((float) $group['total_paid'], 2);
            $group['total_received'] = round((float) $group['total_received'], 2);
            $group['net_balance'] = round($group['total_received'] - $group['total_paid'], 2);
            $groups[] = $group;
        }

        usort($groups, fn ($a, $b) => $b['total_paid'] <=> $a['total_paid'] ?: strcmp($a['cost_center'], $b['cost_center']));

        return $groups;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groupsMap
     * @return list<array<string, mixed>>
     */
    private function finalizeCategoryGroups(array $groupsMap): array
    {
        $groups = [];

        foreach ($groupsMap as $group) {
            $group['expense'] = $this->asList($group['expense']);
            $group['total_expense'] = round(array_sum(array_column($group['expense'], 'total')), 2);
            $groups[] = $group;
        }

        usort($groups, fn ($a, $b) => $b['total_expense'] <=> $a['total_expense'] ?: strcmp($a['cost_center'], $b['cost_center']));

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cashFlowGroups(?string $from, ?string $to, int $days): array
    {
        $groups = [];

        foreach (CostCenter::query()->orderBy('name')->get() as $costCenter) {
            $realized = $this->cashFlow->realized($from, $to, $costCenter->uuid, null);
            $projected = $this->cashFlow->projected(null, null, $days, $costCenter->uuid);

            $groups[] = [
                'cost_center' => $costCenter->name,
                'realized_net' => round($realized['total_in'] - $realized['total_out'], 2),
                'projected_net' => round($projected['total_in'] - $projected['total_out'], 2),
                'expected_final_balance' => round($realized['final_balance'] + $projected['total_in'] - $projected['total_out'], 2),
                'total_in' => round($realized['total_in'] + $projected['total_in'], 2),
                'total_out' => round($realized['total_out'] + $projected['total_out'], 2),
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, float>  $data
     * @return list<array{category: string, total: float}>
     */
    private function asList(array $data): array
    {
        $list = [];

        foreach ($data as $name => $total) {
            $list[] = ['category' => $name, 'total' => $total];
        }

        usort($list, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $list;
    }

    private function resolveCostCenterLabel(?string $costCenterId): string
    {
        if ($costCenterId === null || $costCenterId === '') {
            return 'Todos os centros';
        }

        return CostCenter::query()->where('uuid', $costCenterId)->value('name') ?? 'Centro de custo';
    }

    /**
     * @param  callable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet): void  $configure
     */
    private function streamXlsx(string $filename, string $sheetTitle, callable $configure): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $configure($sheet);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function dailyExport(?string $date = null, ?string $costCenterId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->daily($date, $costCenterId);
        $dateLabel = Carbon::parse($data['date'])->format('d/m/Y');
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);

        return $this->streamXlsx('relatorio-diario.xlsx', 'Diário', function (Worksheet $sheet) use ($data, $dateLabel, $costCenterLabel): void {
            $row = $this->applyXlsxTitleBlock(
                $sheet,
                'Relatório diário',
                [
                    "Data: {$dateLabel}",
                    "Centro de custo: {$costCenterLabel}",
                ],
            );
            $columnCount = 3;

            foreach ($data['groups'] as $group) {
                $sheet->setCellValue("A{$row}", $group['cost_center']);
                $this->applyXlsxSectionBanner($sheet, $row, $columnCount);
                $row++;

                foreach (['Pagamentos realizados' => $group['payments'], 'Recebimentos realizados' => $group['receipts']] as $title => $items) {
                    if (count($items) === 0) {
                        continue;
                    }

                    $sheet->setCellValue("A{$row}", $title);
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    $row++;

                    $headerRow = $row;
                    $sheet->fromArray(['Descrição', 'Categoria', 'Valor'], null, "A{$row}");
                    $row++;

                    $dataStartRow = $row;

                    foreach ($items as $item) {
                        $sheet->fromArray([
                            $item['description'],
                            $item['category'] ?? '',
                            $this->xlsxMoney($item['value']),
                        ], null, "A{$row}");
                        $row++;
                    }

                    $this->applyXlsxColumnHeader($sheet, $headerRow, $columnCount, 'FFF3F4F6');
                    $this->applyXlsxDataArea($sheet, $dataStartRow, $row - 1, $columnCount, 2, false);
                    $this->applyXlsxCurrencyColumns($sheet, 3, 3, $dataStartRow, $row - 1);
                    $row++;
                }

                foreach ([
                    ['Total pago', $group['total_paid']],
                    ['Total recebido', $group['total_received']],
                    ['Saldo do centro', $group['balance']],
                ] as [$label, $value]) {
                    $sheet->setCellValue("A{$row}", $label);
                    $sheet->setCellValue('C'.$row, $this->xlsxMoney($value));
                    $this->applyXlsxFooterRow($sheet, $row, $columnCount);
                    $row++;
                }

                $row++;
            }

            foreach ([
                ['Total geral pago', $data['total_paid']],
                ['Total geral recebido', $data['total_received']],
                ['Saldo geral do dia', $data['balance']],
            ] as [$label, $value]) {
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue('C'.$row, $this->xlsxMoney($value));
                $this->applyXlsxTotalRow($sheet, $row, $columnCount, true);
                $row++;
            }

            $this->applyXlsxColumnWidths($sheet, $columnCount, 36, 22);
        });
    }

    public function weeklyExport(?string $from = null, ?string $to = null, ?string $costCenterId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->weekly($from, $to, $costCenterId);
        $fromLabel = Carbon::parse($data['from'])->format('d/m/Y');
        $toLabel = Carbon::parse($data['to'])->format('d/m/Y');
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);

        return $this->streamXlsx('relatorio-semanal.xlsx', 'Semanal', function (Worksheet $sheet) use ($data, $fromLabel, $toLabel, $costCenterLabel): void {
            $headerRow = $this->applyXlsxTitleBlock(
                $sheet,
                'Relatório semanal',
                [
                    "Período: {$fromLabel} até {$toLabel}",
                    "Centro de custo: {$costCenterLabel}",
                ],
            );
            $columnCount = 4;

            $sheet->fromArray(['Centro de custo', 'Total pago', 'Total recebido', 'Saldo líquido'], null, "A{$headerRow}");

            $row = $headerRow + 1;
            $dataStartRow = $row;

            foreach ($data['groups'] as $group) {
                $sheet->fromArray([
                    $group['cost_center'],
                    $this->xlsxMoney($group['total_paid']),
                    $this->xlsxMoney($group['total_received']),
                    $this->xlsxMoney($group['net_balance']),
                ], null, "A{$row}");
                $row++;
            }

            $dataEndRow = $row - 1;
            $totalRow = $row + 1;

            $sheet->fromArray([
                'Total geral',
                $this->xlsxMoney($data['total_paid']),
                $this->xlsxMoney($data['total_received']),
                $this->xlsxMoney($data['net_balance']),
            ], null, "A{$totalRow}");

            $this->applyXlsxStandardTable(
                $sheet,
                headerRow: $headerRow,
                dataStartRow: $dataStartRow,
                dataEndRow: max($dataStartRow, $dataEndRow),
                totalRow: $totalRow,
                columnCount: $columnCount,
                labelColumns: 1,
                labelBold: true,
                grandTotal: true,
            );
        });
    }

    public function byCategoryExport(?string $from = null, ?string $to = null, ?string $costCenterId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->byCategory($from, $to, $costCenterId);
        $matrix = $data['matrix'];
        $fromLabel = Carbon::parse($data['from'])->format('d/m/Y');
        $toLabel = Carbon::parse($data['to'])->format('d/m/Y');
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);
        $columnKeys = array_column($matrix['columns'], 'key');
        $columnLabels = array_column($matrix['columns'], 'label');

        return $this->streamXlsx('relatorio-por-categoria.xlsx', 'Por categoria', function (Worksheet $sheet) use ($matrix, $fromLabel, $toLabel, $costCenterLabel, $columnKeys, $columnLabels): void {
            $headerRow = $this->applyXlsxTitleBlock(
                $sheet,
                'Relatório por categoria',
                [
                    "Período: {$fromLabel} até {$toLabel}",
                    "Centro de custo: {$costCenterLabel}",
                ],
                sprintf(
                    'Total geral: R$ %s',
                    number_format((float) $matrix['grand_total']['total'], 2, ',', '.'),
                ),
            );

            $columnCount = count($columnLabels) + 2;
            $headers = array_merge(['Descrição'], $columnLabels, ['Total geral']);
            $sheet->fromArray($headers, null, "A{$headerRow}");
            $this->applyXlsxColumnHeader($sheet, $headerRow, $columnCount);

            $row = $headerRow + 1;
            $dataStartRow = $row;

            foreach ($matrix['groups'] as $group) {
                $sheet->setCellValue("A{$row}", $group['cost_center']);
                $this->applyXlsxSectionBanner($sheet, $row, $columnCount);
                $row++;

                foreach ($group['categories'] as $category) {
                    $sheet->setCellValue("A{$row}", "{$category['category']} - Totais");
                    $categoryTotalCells = [];

                    foreach ($columnKeys as $key) {
                        $categoryTotalCells[] = $this->xlsxMoney($category['subtotal']['amounts'][$key] ?? null);
                    }

                    $categoryTotalCells[] = $this->xlsxMoney($category['subtotal']['total']);
                    $sheet->fromArray($categoryTotalCells, null, "B{$row}");
                    $this->applyXlsxSubtotalRow($sheet, $row, $columnCount);
                    $row++;

                    foreach ($category['subcategories'] as $subcategory) {
                        $sheet->setCellValue("A{$row}", '    '.$subcategory['subcategory'].' - Totais');
                        $subtotalCells = [];

                        foreach ($columnKeys as $key) {
                            $subtotalCells[] = $this->xlsxMoney($subcategory['subtotal']['amounts'][$key] ?? null);
                        }

                        $subtotalCells[] = $this->xlsxMoney($subcategory['subtotal']['total']);
                        $sheet->fromArray($subtotalCells, null, "B{$row}");
                        $this->applyXlsxSubtotalRow($sheet, $row, $columnCount, 'FFEFF6FF');
                        $row++;
                    }
                }

                $sheet->setCellValue("A{$row}", "{$group['cost_center']} - Totais");
                $groupTotalCells = [];

                foreach ($columnKeys as $key) {
                    $groupTotalCells[] = $this->xlsxMoney($group['subtotal']['amounts'][$key] ?? null);
                }

                $groupTotalCells[] = $this->xlsxMoney($group['subtotal']['total']);
                $sheet->fromArray($groupTotalCells, null, "B{$row}");
                $this->applyXlsxSubtotalRow($sheet, $row, $columnCount, 'FFBFDBFE');
                $row += 2;
            }

            $grandRow = $row;
            $sheet->setCellValue('A'.$row, 'Total geral');
            $grandCells = [];

            foreach ($columnKeys as $key) {
                $grandCells[] = $this->xlsxMoney($matrix['grand_total']['amounts'][$key] ?? null);
            }

            $grandCells[] = $this->xlsxMoney($matrix['grand_total']['total']);
            $sheet->fromArray($grandCells, null, 'B'.$row);
            $this->applyXlsxTotalRow($sheet, $grandRow, $columnCount, true);

            $this->applyXlsxDataArea($sheet, $dataStartRow, $grandRow - 1, $columnCount, 1, false);
            $this->applyXlsxColumnWidths($sheet, $columnCount, 40, 14);

            $lastColumn = $this->xlsxColumnLetter($columnCount);

            if ($grandRow > $dataStartRow) {
                $sheet->getStyle("{$lastColumn}{$dataStartRow}:{$lastColumn}".($grandRow - 1))
                    ->getFont()
                    ->getColor()
                    ->setARGB('FFDC2626');
            }

            $sheet->getStyle("B{$grandRow}:{$lastColumn}{$grandRow}")
                ->getFont()
                ->getColor()
                ->setARGB('FFDC2626');
        });
    }

    public function monthlySummaryExport(?string $from = null, ?string $to = null, ?string $costCenterId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->monthlySummary($from, $to, $costCenterId);
        $fromLabel = Carbon::parse($data['from'])->format('d/m/Y');
        $toLabel = Carbon::parse($data['to'])->format('d/m/Y');
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);
        $columnKeys = array_column($data['columns'], 'key');
        $columnLabels = array_column($data['columns'], 'label');

        return $this->streamXlsx('relatorio-resumo-mensal.xlsx', 'Resumo mensal', function (Worksheet $sheet) use ($data, $fromLabel, $toLabel, $costCenterLabel, $columnKeys, $columnLabels): void {
            $headerRow = $this->applyXlsxTitleBlock(
                $sheet,
                'Resumo mensal por centro de custo',
                [
                    "Período: {$fromLabel} até {$toLabel}",
                    "Centro de custo: {$costCenterLabel}",
                ],
                sprintf(
                    'Total geral: R$ %s | Média mês: R$ %s',
                    number_format((float) $data['grand_total']['total'], 2, ',', '.'),
                    number_format((float) $data['monthly_average'], 2, ',', '.'),
                ),
            );
            $row = $headerRow;
            $headers = array_merge(['Centro de custo'], $columnLabels, ['Total']);
            $columnCount = count($headers);
            $lastColumn = $this->xlsxColumnLetter($columnCount);

            $sheet->fromArray($headers, null, "A{$row}");
            $row++;

            $dataStartRow = $row;

            foreach ($data['rows'] as $item) {
                $cells = [$item['cost_center']];

                foreach ($columnKeys as $key) {
                    $cells[] = $this->xlsxMoney($item['amounts'][$key] ?? null);
                }

                $cells[] = $this->xlsxMoney($item['total']);
                $sheet->fromArray($cells, null, "A{$row}");
                $row++;
            }

            $dataEndRow = $row - 1;
            $totalRow = $row;
            $totalCells = ['Total'];

            foreach ($columnKeys as $key) {
                $totalCells[] = $this->xlsxMoney($data['grand_total']['amounts'][$key] ?? null);
            }

            $totalCells[] = $this->xlsxMoney($data['grand_total']['total']);
            $sheet->fromArray($totalCells, null, "A{$row}");
            $row++;

            $averageRow = $row;
            $mergeUntilColumn = $this->xlsxColumnLetter(max(1, $columnCount - 1));
            $sheet->setCellValue("A{$averageRow}", 'Média mês');
            $sheet->mergeCells("A{$averageRow}:{$mergeUntilColumn}{$averageRow}");
            $sheet->setCellValue("{$lastColumn}{$averageRow}", $this->xlsxMoney($data['monthly_average']));

            $this->applyMonthlySummarySheetStyles(
                $sheet,
                headerRow: $headerRow,
                dataStartRow: $dataStartRow,
                dataEndRow: max($dataStartRow, $dataEndRow),
                totalRow: $totalRow,
                averageRow: $averageRow,
                columnCount: $columnCount,
            );
        });
    }

    private function xlsxColumnLetter(int $columnIndex): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex);
    }

    private function xlsxMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function xlsxThinBorder(): array
    {
        return [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FFE5E7EB'],
        ];
    }

    private function xlsxCurrencyFormat(): string
    {
        return '"R$ "#,##0.00;-"R$ "#,##0.00';
    }

    /**
     * @param  list<string>  $subtitleLines
     */
    private function applyXlsxTitleBlock(Worksheet $sheet, string $title, array $subtitleLines = [], ?string $summaryLine = null): int
    {
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 2;

        foreach ($subtitleLines as $line) {
            $sheet->setCellValue("A{$row}", $line);
            $row++;
        }

        if ($summaryLine !== null) {
            $sheet->setCellValue("A{$row}", $summaryLine);
            $row++;
        }

        if ($row > 2) {
            $sheet->getStyle('A2:A'.($row - 1))->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF4B5563'));
        }

        return $row + 1;
    }

    private function applyXlsxColumnHeader(Worksheet $sheet, int $row, int $columnCount, string $fillArgb = 'FFDBEAFE'): void
    {
        $lastColumn = $this->xlsxColumnLetter($columnCount);
        $range = "A{$row}:{$lastColumn}{$row}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => $fillArgb],
            ],
            'borders' => ['allBorders' => $this->xlsxThinBorder()],
        ]);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if ($columnCount > 1) {
            $sheet->getStyle("B{$row}:{$lastColumn}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function applyXlsxSectionBanner(Worksheet $sheet, int $row, int $columnCount): void
    {
        $lastColumn = $this->xlsxColumnLetter($columnCount);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFDBEAFE'],
            ],
        ]);
    }

    private function applyXlsxDataArea(
        Worksheet $sheet,
        int $startRow,
        int $endRow,
        int $columnCount,
        int $labelColumns = 1,
        bool $labelBold = false,
    ): void {
        if ($endRow < $startRow) {
            return;
        }

        $lastColumn = $this->xlsxColumnLetter($columnCount);
        $sheet->getStyle("A{$startRow}:{$lastColumn}{$endRow}")->applyFromArray([
            'borders' => ['allBorders' => $this->xlsxThinBorder()],
        ]);

        if ($labelBold) {
            $labelColumn = $this->xlsxColumnLetter($labelColumns);
            $sheet->getStyle("A{$startRow}:{$labelColumn}{$endRow}")->getFont()->setBold(true);
        }

        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if ($columnCount > $labelColumns) {
            $this->applyXlsxCurrencyColumns($sheet, $labelColumns + 1, $columnCount, $startRow, $endRow);
        }
    }

    private function applyXlsxCurrencyColumns(
        Worksheet $sheet,
        int $startColumnIndex,
        int $endColumnIndex,
        int $startRow,
        int $endRow,
    ): void {
        $startColumn = $this->xlsxColumnLetter($startColumnIndex);
        $endColumn = $this->xlsxColumnLetter($endColumnIndex);
        $range = "{$startColumn}{$startRow}:{$endColumn}{$endRow}";

        $sheet->getStyle($range)->getNumberFormat()->setFormatCode($this->xlsxCurrencyFormat());
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function applyXlsxTotalRow(Worksheet $sheet, int $row, int $columnCount, bool $grand = false): void
    {
        $lastColumn = $this->xlsxColumnLetter($columnCount);
        $borders = ['allBorders' => $this->xlsxThinBorder()];

        if ($grand) {
            $borders['top'] = [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['argb' => 'FF93C5FD'],
            ];
        }

        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFDBEAFE'],
            ],
            'borders' => $borders,
        ]);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if ($columnCount > 1) {
            $this->applyXlsxCurrencyColumns($sheet, 2, $columnCount, $row, $row);
        }
    }

    private function applyXlsxSubtotalRow(Worksheet $sheet, int $row, int $columnCount, string $fillArgb = 'FFDBEAFE'): void
    {
        $lastColumn = $this->xlsxColumnLetter($columnCount);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => $fillArgb],
            ],
            'borders' => ['allBorders' => $this->xlsxThinBorder()],
        ]);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if ($columnCount > 1) {
            $this->applyXlsxCurrencyColumns($sheet, 2, $columnCount, $row, $row);
        }
    }

    private function applyXlsxFooterRow(Worksheet $sheet, int $row, int $columnCount): void
    {
        $lastColumn = $this->xlsxColumnLetter($columnCount);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF9FAFB'],
            ],
            'borders' => ['allBorders' => $this->xlsxThinBorder()],
        ]);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if ($columnCount > 1) {
            $this->applyXlsxCurrencyColumns($sheet, $columnCount, $columnCount, $row, $row);
        }
    }

    private function applyXlsxAverageRow(Worksheet $sheet, int $row, int $columnCount): void
    {
        $lastColumn = $this->xlsxColumnLetter($columnCount);
        $mergeUntil = $this->xlsxColumnLetter(max(1, $columnCount - 1));

        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFFEDD5'],
            ],
            'borders' => ['allBorders' => $this->xlsxThinBorder()],
        ]);
        $sheet->getStyle("A{$row}:{$mergeUntil}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$lastColumn}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->applyXlsxCurrencyColumns($sheet, $columnCount, $columnCount, $row, $row);
    }

    private function applyXlsxColumnWidths(Worksheet $sheet, int $columnCount, float $firstWidth = 30, float $defaultWidth = 16): void
    {
        $sheet->getColumnDimension('A')->setWidth($firstWidth);

        for ($column = 2; $column <= $columnCount; $column++) {
            $sheet->getColumnDimension($this->xlsxColumnLetter($column))->setWidth($defaultWidth);
        }
    }

    private function applyXlsxStandardTable(
        Worksheet $sheet,
        int $headerRow,
        int $dataStartRow,
        int $dataEndRow,
        ?int $totalRow,
        int $columnCount,
        int $labelColumns = 1,
        bool $labelBold = false,
        bool $grandTotal = false,
        string $headerFill = 'FFDBEAFE',
    ): void {
        $this->applyXlsxColumnHeader($sheet, $headerRow, $columnCount, $headerFill);
        $this->applyXlsxDataArea($sheet, $dataStartRow, $dataEndRow, $columnCount, $labelColumns, $labelBold);

        if ($totalRow !== null) {
            $this->applyXlsxTotalRow($sheet, $totalRow, $columnCount, $grandTotal);
        }

        $this->applyXlsxColumnWidths($sheet, $columnCount);
    }

    private function applyMonthlySummarySheetStyles(
        Worksheet $sheet,
        int $headerRow,
        int $dataStartRow,
        int $dataEndRow,
        int $totalRow,
        int $averageRow,
        int $columnCount,
    ): void {
        $this->applyXlsxColumnHeader($sheet, $headerRow, $columnCount);
        $this->applyXlsxDataArea($sheet, $dataStartRow, $dataEndRow, $columnCount, 1, true);
        $this->applyXlsxTotalRow($sheet, $totalRow, $columnCount, true);
        $this->applyXlsxAverageRow($sheet, $averageRow, $columnCount);
        $this->applyXlsxColumnWidths($sheet, $columnCount);
    }

    public function byCostCenterExport(?string $costCenterId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->byCostCenter($costCenterId);
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);

        return $this->streamXlsx('relatorio-por-centro-de-custo.xlsx', 'Centros de custo', function (Worksheet $sheet) use ($data, $costCenterLabel): void {
            $headerRow = $this->applyXlsxTitleBlock(
                $sheet,
                'Relatório por centro de custo',
                ["Centro de custo: {$costCenterLabel}"],
            );
            $columnCount = 5;

            $sheet->fromArray(['Centro de custo', 'Saldo inicial', 'Entradas', 'Saídas', 'Saldo'], null, "A{$headerRow}");

            $row = $headerRow + 1;
            $dataStartRow = $row;

            foreach ($data['rows'] as $item) {
                $sheet->fromArray([
                    $item['cost_center'],
                    $this->xlsxMoney($item['initial_balance']),
                    $this->xlsxMoney($item['income']),
                    $this->xlsxMoney($item['expense']),
                    $this->xlsxMoney($item['balance']),
                ], null, "A{$row}");
                $row++;
            }

            $this->applyXlsxStandardTable(
                $sheet,
                headerRow: $headerRow,
                dataStartRow: $dataStartRow,
                dataEndRow: max($dataStartRow, $row - 1),
                totalRow: null,
                columnCount: $columnCount,
                labelColumns: 1,
                labelBold: true,
            );
        });
    }

    public function cashFlowExport(?string $from = null, ?string $to = null, int $days = 30, ?string $costCenterId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->cashFlow($from, $to, $days, $costCenterId);
        $fromLabel = Carbon::parse($data['realized']['from'])->format('d/m/Y');
        $toLabel = Carbon::parse($data['realized']['to'])->format('d/m/Y');
        $costCenterLabel = $this->resolveCostCenterLabel($costCenterId);

        return $this->streamXlsx('demonstrativo-fluxo-caixa.xlsx', 'Demonstrativo', function (Worksheet $sheet) use ($data, $fromLabel, $toLabel, $costCenterLabel, $days): void {
            $headerRow = $this->applyXlsxTitleBlock(
                $sheet,
                'Demonstrativo de fluxo de caixa',
                [
                    "Período: {$fromLabel} até {$toLabel}",
                    "Projeção: {$days} dias · Centro de custo: {$costCenterLabel}",
                ],
            );
            $columnCount = 6;

            $sheet->fromArray(['Centro de custo', 'Resultado realizado', 'Resultado projetado', 'Saldo final esperado', 'Entradas', 'Saídas'], null, "A{$headerRow}");

            $row = $headerRow + 1;
            $dataStartRow = $row;

            foreach ($data['groups'] as $group) {
                $sheet->fromArray([
                    $group['cost_center'],
                    $this->xlsxMoney($group['realized_net']),
                    $this->xlsxMoney($group['projected_net']),
                    $this->xlsxMoney($group['expected_final_balance']),
                    $this->xlsxMoney($group['total_in']),
                    $this->xlsxMoney($group['total_out']),
                ], null, "A{$row}");
                $row++;
            }

            $totalRow = $row + 1;
            $sheet->fromArray([
                'Total geral',
                $this->xlsxMoney($data['comparative']['realized_net']),
                $this->xlsxMoney($data['comparative']['projected_net']),
                $this->xlsxMoney($data['comparative']['expected_final_balance']),
                $this->xlsxMoney($data['realized']['total_in'] + $data['projected']['total_in']),
                $this->xlsxMoney($data['realized']['total_out'] + $data['projected']['total_out']),
            ], null, "A{$totalRow}");

            $this->applyXlsxStandardTable(
                $sheet,
                headerRow: $headerRow,
                dataStartRow: $dataStartRow,
                dataEndRow: max($dataStartRow, $row - 1),
                totalRow: $totalRow,
                columnCount: $columnCount,
                labelColumns: 1,
                labelBold: true,
                grandTotal: true,
            );
        });
    }
}
