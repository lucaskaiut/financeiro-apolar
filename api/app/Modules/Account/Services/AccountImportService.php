<?php

namespace App\Modules\Account\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Account\Support\XlsxRowReader;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Category\Enums\CategoryType;
use App\Modules\Category\Models\Category;
use App\Modules\User\Models\User;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Normalizer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class AccountImportService
{
    /** @var list<string> */
    private const PAID_STATUSES = ['pago', 'paga', 'baixada', 'baixado', 'liquidado', 'liquidada', 'settled', 'paid'];

    /** @var list<string> */
    private const OPEN_STATUSES = ['a vencer', 'vencido', 'vencida', 'aberto', 'aberta', 'previsto', 'open', 'overdue'];

    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Importa contas a pagar a partir de uma planilha XLSX.
     *
     * Formato Apolar (aba "BASE DE DADOS"):
     * - GRUPO              → categoria (criada automaticamente)
     * - TIPO DE DESPESA    → subcategoria (criada automaticamente)
     * - TIPO DE DESPESA 2  → descrição (senão usa TIPO DE DESPESA)
     * - VENCIMENTO         → vencimento
     * - PGTO               → data de pagamento (se vazia e a conta estiver paga, usa o vencimento)
     * - STATUS             → Pago/Baixada liquida; A Vencer/Vencido permanece em aberto
     * - $ REALIZADO / R$ PREVISTO → valor
     * - OBSERVAÇÃO         → observação
     *
     * Formato legado: Data, Histórico, Débito/Valor, GRUPO, TIPO, CONSIDERAR.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importXlsx(UploadedFile $file, string $bankAccountId, User $user, ?string $costCenterId = null): array
    {
        $rows = $this->readRows($file->getRealPath());

        if (count($rows) < 2) {
            throw new RuntimeException('A planilha não possui linhas de dados.');
        }

        $headers = array_map(fn ($cell) => $this->normalize($cell), $rows[0]);
        $columns = $this->mapColumns($headers);

        if ($columns['category'] === null) {
            throw new RuntimeException('Não foi possível identificar a coluna "GRUPO" no cabeçalho.');
        }

        if ($columns['description'] === null && $columns['descriptionDetail'] === null) {
            throw new RuntimeException('Não foi possível identificar a coluna de descrição ("Histórico" ou "TIPO DE DESPESA") no cabeçalho.');
        }

        if ($columns['value'] === null && $columns['forecast'] === null && $columns['realized'] === null) {
            throw new RuntimeException('Não foi possível identificar a coluna de valor (Débito/Valor, R$ PREVISTO ou $ REALIZADO) no cabeçalho.');
        }

        /** @var array<string, Category> $categories */
        $categories = [];
        /** @var array<string, ?Category> $subcategories */
        $subcategories = [];

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $rows,
            $columns,
            $bankAccountId,
            $costCenterId,
            $user,
            &$categories,
            &$subcategories,
            &$imported,
            &$skipped,
        ): void {
            foreach (array_slice($rows, 1) as $row) {
                if (! $this->shouldImport($row, $columns['flag'])) {
                    $skipped++;

                    continue;
                }

                $categoryName = trim((string) ($row[$columns['category']] ?? ''));
                $subcategoryName = $columns['subcategory'] !== null
                    ? trim((string) ($row[$columns['subcategory']] ?? ''))
                    : '';
                $description = $this->resolveDescription($row, $columns);
                $dueDate = $columns['dueDate'] !== null ? $this->parseDate($row[$columns['dueDate']] ?? null) : null;
                $paid = $this->isPaid($row, $columns);
                $amount = $this->resolveAmount($row, $columns, $paid);
                $observation = $columns['observation'] !== null
                    ? trim((string) ($row[$columns['observation']] ?? ''))
                    : '';

                if ($description === '' || $amount === null || $dueDate === null || $categoryName === '') {
                    $skipped++;

                    continue;
                }

                $category = $this->findOrCreateCategory($categories, $categoryName);
                $subcategory = $this->findOrCreateSubcategory($subcategories, $category, $subcategoryName);
                $paidDate = $paid ? $this->resolvePaidDate($row, $columns, $dueDate) : null;

                $account = FinancialAccount::query()->create([
                    'type' => AccountType::Payable,
                    'description' => mb_substr($description, 0, 255),
                    'value' => $amount,
                    'due_date' => $dueDate,
                    'paid_date' => $paidDate,
                    'bank_account_id' => $bankAccountId,
                    'cost_center_id' => $costCenterId,
                    'category_id' => $category->uuid,
                    'subcategory_id' => $subcategory?->uuid,
                    'observation' => $observation !== '' ? $observation : null,
                    'status' => $paid ? AccountStatus::Settled : AccountStatus::Open,
                ]);

                if ($paid) {
                    Settlement::query()->create([
                        'account_id' => $account->getKey(),
                        'value' => $amount,
                        'settled_at' => $paidDate,
                        'method' => null,
                        'user_id' => $user->getKey(),
                    ]);
                }

                $this->audit->recordEntity(
                    $user,
                    AuditAction::FinancialCreate,
                    'account',
                    $account->uuid,
                    ['description' => $account->description, 'settled' => $paid, 'source' => 'xlsx_import'],
                );

                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(string $path): array
    {
        if (XlsxRowReader::canRead($path)) {
            return (new XlsxRowReader)->read($path);
        }

        return $this->readRowsWithPhpSpreadsheet($path);
    }

    /**
     * Fallback para .xls e arquivos que o ZipArchive não abre.
     *
     * @return list<list<mixed>>
     */
    private function readRowsWithPhpSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        if (method_exists($reader, 'setIgnoreRowsWithNoCells')) {
            $reader->setIgnoreRowsWithNoCells(true);
        }

        $names = $reader->listWorksheetNames($path);
        $preferred = $this->preferredSheetName($names);

        if ($preferred !== null) {
            $reader->setLoadSheetsOnly([$preferred]);
        }

        $spreadsheet = $reader->load($path, IReader::READ_DATA_ONLY | IReader::IGNORE_EMPTY_CELLS | IReader::IGNORE_ROWS_WITH_NO_CELLS);
        $sheet = $preferred !== null
            ? ($spreadsheet->getSheetByName($preferred) ?? $spreadsheet->getActiveSheet())
            : $this->resolveSheet($spreadsheet);
        $rows = $this->extractRows($sheet);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * @param  list<string>  $names
     */
    private function preferredSheetName(array $names): ?string
    {
        foreach ($names as $name) {
            if ($this->normalize($name) === 'base de dados') {
                return $name;
            }
        }

        return null;
    }

    private function resolveSheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($this->normalize($sheet->getTitle()) === 'base de dados') {
                return $sheet;
            }
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $headers = array_map(
                fn ($cell) => $this->normalize($cell),
                $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().'1', null, true, false)[0] ?? [],
            );

            if ($this->findColumn($headers, 'grupo') === null) {
                continue;
            }

            if (
                $this->findColumn($headers, 'historico') !== null
                || $this->findColumn($headers, 'tipo de despesa') !== null
                || $this->findColumn($headers, 'vencimento') !== null
            ) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @return list<list<mixed>>
     */
    private function extractRows(Worksheet $sheet): array
    {
        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = min($sheet->getHighestDataRow(), 20000);
        $rows = [];
        $emptyStreak = 0;

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0] ?? [];

            if ($this->isBlankRow($row)) {
                $emptyStreak++;

                if ($rows !== [] && $emptyStreak >= 25) {
                    break;
                }

                continue;
            }

            $emptyStreak = 0;
            $rows[] = $row;
        }

        return $rows;
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

    /**
     * @param  list<string>  $headers
     * @return array{
     *     dueDate: ?int,
     *     description: ?int,
     *     descriptionDetail: ?int,
     *     value: ?int,
     *     forecast: ?int,
     *     realized: ?int,
     *     category: ?int,
     *     subcategory: ?int,
     *     flag: ?int,
     *     paidDate: ?int,
     *     status: ?int,
     *     prevReal: ?int,
     *     observation: ?int
     * }
     */
    private function mapColumns(array $headers): array
    {
        return [
            'dueDate' => $this->findColumn($headers, 'vencimento') ?? $this->findColumn($headers, 'data'),
            'description' => $this->findColumn($headers, 'historico') ?? $this->findColumn($headers, 'tipo de despesa'),
            'descriptionDetail' => $this->findColumn($headers, 'tipo de despesa 2'),
            'value' => $this->findColumn($headers, 'debito') ?? $this->findColumn($headers, 'valor'),
            'forecast' => $this->findColumn($headers, 'previsto'),
            'realized' => $this->findColumn($headers, 'realizado'),
            'category' => $this->findColumn($headers, 'grupo'),
            'subcategory' => $this->findColumn($headers, 'tipo de despesa') ?? $this->findColumn($headers, 'tipo'),
            'flag' => $this->findColumn($headers, 'considerar'),
            'paidDate' => $this->findColumn($headers, 'pgto'),
            'status' => $this->findColumn($headers, 'status'),
            'prevReal' => $this->findColumn($headers, 'prev / real') ?? $this->findColumn($headers, 'prev'),
            'observation' => $this->findColumn($headers, 'observacao'),
        ];
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $columns
     */
    private function resolveDescription(array $row, array $columns): string
    {
        $detail = $columns['descriptionDetail'] !== null
            ? trim((string) ($row[$columns['descriptionDetail']] ?? ''))
            : '';

        if ($detail !== '') {
            return $detail;
        }

        if ($columns['description'] !== null) {
            return trim((string) ($row[$columns['description']] ?? ''));
        }

        return '';
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $columns
     */
    private function resolveAmount(array $row, array $columns, bool $paid): ?float
    {
        $realized = $columns['realized'] !== null ? $this->parseMoney($row[$columns['realized']] ?? null) : null;
        $forecast = $columns['forecast'] !== null ? $this->parseMoney($row[$columns['forecast']] ?? null) : null;
        $legacy = $columns['value'] !== null ? $this->parseMoney($row[$columns['value']] ?? null) : null;

        $preferred = $paid
            ? $this->firstPositive($realized, $forecast, $legacy)
            : $this->firstPositive($forecast, $realized, $legacy);

        return $preferred !== null ? round($preferred, 2) : null;
    }

    private function firstPositive(?float ...$values): ?float
    {
        foreach ($values as $value) {
            if ($value !== null && abs($value) > 0) {
                return abs($value);
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $columns
     */
    private function isPaid(array $row, array $columns): bool
    {
        if ($columns['status'] !== null) {
            $status = $this->normalize($row[$columns['status']] ?? null);

            if (in_array($status, self::PAID_STATUSES, true)) {
                return true;
            }

            if (in_array($status, self::OPEN_STATUSES, true) || $status !== '') {
                return false;
            }
        }

        if ($columns['prevReal'] !== null) {
            $prevReal = $this->normalize($row[$columns['prevReal']] ?? null);

            if (in_array($prevReal, ['realizado', 'real', 'pago'], true)) {
                return true;
            }

            if (in_array($prevReal, ['previsto', 'prev'], true)) {
                return false;
            }
        }

        if ($columns['paidDate'] !== null) {
            return $this->parseDate($row[$columns['paidDate']] ?? null) !== null;
        }

        return true;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|null>  $columns
     */
    private function resolvePaidDate(array $row, array $columns, string $dueDate): string
    {
        if ($columns['paidDate'] !== null) {
            $paidDate = $this->parseDate($row[$columns['paidDate']] ?? null);

            if ($paidDate !== null) {
                return $paidDate;
            }
        }

        return $dueDate;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function shouldImport(array $row, ?int $flagColumn): bool
    {
        if ($flagColumn === null) {
            return true;
        }

        $flag = $this->normalize($row[$flagColumn] ?? null);

        return $flag === '' || in_array($flag, ['sim', 's', 'x', 'yes', '1'], true);
    }

    /**
     * @param  array<string, Category>  $cache
     */
    private function findOrCreateCategory(array &$cache, string $name): Category
    {
        $key = mb_strtolower($name);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $category = Category::query()
            ->whereNull('parent_id')
            ->where('type', CategoryType::Expense->value)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();

        if ($category === null) {
            $category = Category::query()->create([
                'name' => $name,
                'type' => CategoryType::Expense,
                'status' => 'active',
            ]);
        }

        return $cache[$key] = $category;
    }

    /**
     * @param  array<string, ?Category>  $cache
     */
    private function findOrCreateSubcategory(array &$cache, Category $category, string $name): ?Category
    {
        $name = trim($name);

        if ($name === '' || mb_strtolower($name) === mb_strtolower($category->name)) {
            return null;
        }

        $key = $category->uuid.'::'.mb_strtolower($name);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $subcategory = Category::query()
            ->where('parent_id', $category->uuid)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($subcategory === null) {
            $subcategory = Category::query()->create([
                'name' => $name,
                'type' => CategoryType::Expense,
                'parent_id' => $category->uuid,
                'status' => 'active',
            ]);
        }

        return $cache[$key] = $subcategory;
    }

    private function normalize(mixed $value): string
    {
        return $this->removeAccents(mb_strtolower(trim((string) $value)));
    }

    /**
     * Remove acentos usando a extensão intl (funciona em Alpine/musl,
     * onde o iconv //TRANSLIT não translitera corretamente).
     */
    private function removeAccents(string $value): string
    {
        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);

        if ($decomposed === false) {
            return $value;
        }

        return preg_replace('/[\x{0300}-\x{036F}]/u', '', $decomposed) ?? $value;
    }

    /**
     * Prefere correspondência exata; senão, o cabeçalho mais curto que contém o termo
     * (ex.: "tipo de despesa" ganha de "tipo de despesa 2").
     *
     * @param  list<string>  $headers
     */
    private function findColumn(array $headers, string $needle): ?int
    {
        $best = null;
        $bestLength = PHP_INT_MAX;

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            if ($header === $needle) {
                return $index;
            }

            if (str_contains($header, $needle) && strlen($header) < $bestLength) {
                $best = $index;
                $bestLength = strlen($header);
            }
        }

        return $best;
    }

    private function parseMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = str_replace(['R$', 'r$', ' '], '', trim((string) $value));

        if ($s === '') {
            return null;
        }

        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
        }

        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : null;
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

        $s = trim((string) $value);

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
}
