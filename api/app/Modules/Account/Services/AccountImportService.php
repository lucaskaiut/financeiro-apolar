<?php

namespace App\Modules\Account\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Category\Enums\CategoryType;
use App\Modules\Category\Models\Category;
use App\Modules\User\Models\User;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Normalizer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class AccountImportService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Importa contas a pagar a partir de uma planilha XLSX.
     *
     * Colunas esperadas (detectadas pelo cabeçalho):
     * - Data        → vencimento
     * - Histórico   → descrição
     * - Débito (R$) → valor
     * - GRUPO       → categoria (criada/reatilizada automaticamente)
     * - TIPO        → subcategoria (criada/reatilizada sob a categoria)
     * - CONSIDERAR  → importa apenas linhas vazias ou "SIM"
     *
     * @return array{imported: int, skipped: int}
     */
    public function importXlsx(UploadedFile $file, string $costCenterId, User $user): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false);
        $spreadsheet->disconnectWorksheets();

        if (count($rows) < 2) {
            throw new RuntimeException('A planilha não possui linhas de dados.');
        }

        $headers = array_map(fn ($cell) => $this->normalize($cell), $rows[0]);

        $colDate = $this->findColumn($headers, 'data');
        $colDescription = $this->findColumn($headers, 'historico');
        $colValue = $this->findColumn($headers, 'debito') ?? $this->findColumn($headers, 'valor');
        $colSubcategory = $this->findColumn($headers, 'tipo');
        $colFlag = $this->findColumn($headers, 'considerar');
        $colCategory = $this->findColumn($headers, 'grupo');

        if ($colDescription === null || $colValue === null) {
            throw new RuntimeException('Não foi possível identificar as colunas "Histórico" e a coluna de valor (Débito/Valor) no cabeçalho.');
        }

        if ($colCategory === null) {
            throw new RuntimeException('Não foi possível identificar a coluna "GRUPO" no cabeçalho.');
        }

        /** @var array<string, Category> $categories */
        $categories = [];
        /** @var array<string, ?Category> $subcategories */
        $subcategories = [];

        $imported = 0;
        $skipped = 0;

        foreach (array_slice($rows, 1) as $row) {
            $flag = $this->normalize($row[$colFlag] ?? null);

            if ($flag !== '' && ! in_array($flag, ['sim', 's', 'x', 'yes', '1'], true)) {
                $skipped++;

                continue;
            }

            $description = trim((string) ($row[$colDescription] ?? ''));
            $value = $this->parseMoney($row[$colValue] ?? null);
            $date = $colDate !== null ? $this->parseDate($row[$colDate] ?? null) : null;
            $categoryName = trim((string) ($row[$colCategory] ?? ''));
            $subcategoryName = $colSubcategory !== null ? trim((string) ($row[$colSubcategory] ?? '')) : '';

            if ($description === '' || $value === null || abs($value) <= 0 || $date === null || $categoryName === '') {
                $skipped++;

                continue;
            }

            $category = $this->findOrCreateCategory($categories, $categoryName);
            $subcategory = $this->findOrCreateSubcategory($subcategories, $category, $subcategoryName);

            $amount = round(abs($value), 2);

            $account = FinancialAccount::query()->create([
                'type' => AccountType::Payable,
                'description' => $description,
                'value' => $amount,
                'due_date' => $date,
                'paid_date' => $date,
                'cost_center_id' => $costCenterId,
                'category_id' => $category->uuid,
                'subcategory_id' => $subcategory?->uuid,
                'status' => AccountStatus::Settled,
            ]);

            Settlement::query()->create([
                'account_id' => $account->getKey(),
                'value' => $amount,
                'settled_at' => $date,
                'method' => null,
                'user_id' => $user->getKey(),
            ]);

            $this->audit->recordEntity(
                $user,
                AuditAction::FinancialCreate,
                'account',
                $account->uuid,
                ['description' => $account->description, 'settled' => true, 'source' => 'xlsx_import'],
            );

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
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
            ->where('name', $name)
            ->whereNull('parent_id')
            ->where('type', CategoryType::Expense->value)
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
            ->where('name', $name)
            ->where('parent_id', $category->uuid)
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
     * @param  list<string>  $headers
     */
    private function findColumn(array $headers, string $needle): ?int
    {
        foreach ($headers as $index => $header) {
            if ($header !== '' && str_contains($header, $needle)) {
                return $index;
            }
        }

        return null;
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

        // 1.234,56 → remove os pontos de milhar e converte a vírgula decimal
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

        // dd/mm/aaaa ou dd/mm/aa (também com - ou .)
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

        // aaaa-mm-dd
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }
}
