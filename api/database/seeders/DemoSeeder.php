<?php

namespace Database\Seeders;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\Category\Models\Category;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Demonstração de agosto/2026.
 *
 * - Limpa todos os dados, exceto tenants, usuários e ACL.
 * - Cria contas bancárias, categorias, centros de custo, cartão e lançamentos.
 * - Gera extratos (OFX) para cada conta bancária e a fatura do cartão (XLSX)
 *   para importação pela interface.
 */
class DemoSeeder extends Seeder
{
    /** @var list<string> */
    private const DATA_TABLES = [
        'account_allocations',
        'account_documents',
        'account_imports',
        'audit_logs',
        'bank_accounts',
        'bank_transactions',
        'cache',
        'cache_locks',
        'categories',
        'companies',
        'conversations',
        'cost_centers',
        'credit_card_invoices',
        'credit_cards',
        'failed_jobs',
        'financial_accounts',
        'job_batches',
        'jobs',
        'messages',
        'password_reset_tokens',
        'personal_access_tokens',
        'reconciliations',
        'recurrences',
        'sessions',
        'settlements',
        'transfers',
    ];

    public function run(): void
    {
        $this->cleanData();

        $tenant = Tenant::query()->firstOrFail();
        $user = User::query()->first();

        Model::unguard();

        try {
            $demo = $this->seed($tenant, $user);
        } finally {
            Model::reguard();
        }

        $files = $this->generateFiles($demo);

        $this->summary($demo, $files);
    }

    private function cleanData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::DATA_TABLES as $table) {
            DB::table($table)->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command?->info('Dados antigos removidos (tenants, usuários e ACL preservados).');
    }

    /**
     * @return array<string, mixed>
     */
    private function seed(Tenant $tenant, ?User $user): array
    {
        $tid = $tenant->getKey();
        $userId = $user?->getKey();

        // Categorias
        $vendas = $this->category($tid, 'Vendas', 'income', '#10b981');
        $servicos = $this->category($tid, 'Serviços', 'income', '#14b8a6');
        $fornecedores = $this->category($tid, 'Fornecedores', 'expense', '#ef4444');
        $folha = $this->category($tid, 'Folha de Pagamento', 'expense', '#f97316');
        $impostos = $this->category($tid, 'Impostos', 'expense', '#8b5cf6');
        $infra = $this->category($tid, 'Infraestrutura', 'expense', '#64748b');
        $alimentacao = $this->category($tid, 'Alimentação', 'expense', '#f59e0b');
        $combustivel = $this->category($tid, 'Combustível', 'expense', '#0ea5e9');

        // Centros de custo
        $adm = $this->costCenter($tid, 'Administrativo');
        $comercial = $this->costCenter($tid, 'Comercial');
        $obraA = $this->costCenter($tid, 'Obra A');

        // Contas bancárias
        $bb = $this->bankAccount($tid, 'Banco do Brasil', 'Banco do Brasil', '001', '123456-7', 50000);
        $itau = $this->bankAccount($tid, 'Itaú', 'Itaú', '341', '98765-4', 35000);

        // Cartão de crédito
        $card = CreditCard::query()->create([
            'tenant_id' => $tid,
            'name' => 'Mastercard Black',
            'institution' => 'Itaú',
            'limit' => 30000,
            'closing_day' => 10,
            'due_day' => 15,
            'bank_account_id' => $itau,
            'status' => 'active',
        ])->uuid;

        // Histórico realizado (julho/2026) para fluxo de caixa e dashboard.
        $this->settled($tid, $userId, AccountType::Receivable, 'Vendas 07/2026', 'Clientes diversos', $bb, $vendas, $comercial, 28000, '2026-07-05');
        $this->settled($tid, $userId, AccountType::Receivable, 'Serviços 07/2026', 'Clientes recorrentes', $itau, $servicos, $comercial, 15000, '2026-07-10');
        $this->settled($tid, $userId, AccountType::Payable, 'Folha de pagamento 07/2026', 'Colaboradores', $bb, $folha, $adm, 18000, '2026-07-28');
        $this->settled($tid, $userId, AccountType::Payable, 'Impostos 07/2026', 'Receita Federal', $itau, $impostos, $adm, 4500, '2026-07-22');

        // Lançamentos em aberto (agosto/2026) — conciliáveis com o extrato.
        $this->open($tid, AccountType::Receivable, 'Cliente Alfa', 'Alfa Comércio', $bb, $vendas, $comercial, 12500, '2026-08-05');
        $this->open($tid, AccountType::Payable, 'Aluguel do galpão', 'Imobiliária Central', $bb, $infra, $adm, 5200, '2026-08-10');
        $this->open($tid, AccountType::Receivable, 'Cliente Beta', 'Beta Serviços', $itau, $servicos, $comercial, 8400, '2026-08-08');
        $this->open($tid, AccountType::Payable, 'Fornecedor de matéria-prima', 'Fornecedor Alfa', $itau, $fornecedores, $obraA, 6750, '2026-08-12');

        // Compras no cartão já lançadas (serão deduplicadas na importação da fatura).
        $this->cardPurchase($tid, $card, 'Mercado Central', $alimentacao, $adm, 420.50, '2026-08-03', '2026-08-15');
        $this->cardPurchase($tid, $card, 'Posto Shell', $combustivel, $adm, 300.00, '2026-08-05', '2026-08-15');
        $this->cardPurchase($tid, $card, 'Restaurante La Cucina', $alimentacao, $comercial, 180.90, '2026-08-08', '2026-08-15');

        return [
            'bank_accounts' => [
                'bb' => ['id' => $bb, 'name' => 'Banco do Brasil'],
                'itau' => ['id' => $itau, 'name' => 'Itaú'],
            ],
            'credit_card' => ['id' => $card, 'name' => 'Mastercard Black'],
        ];
    }

    /**
     * @param  array<string, mixed>  $demo
     * @return list<string>
     */
    private function generateFiles(array $demo): array
    {
        $dir = storage_path('app/demo');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bbFile = $this->buildOfx('banco-do-brasil-agosto-2026.ofx', [
            ['type' => 'credit', 'date' => '20260805', 'value' => 12500.00, 'fitid' => 'BB-0001', 'memo' => 'CLIENTE ALFA'],
            ['type' => 'debit', 'date' => '20260810', 'value' => 5200.00, 'fitid' => 'BB-0002', 'memo' => 'ALUGUEL DO GALPAO'],
            ['type' => 'credit', 'date' => '20260806', 'value' => 3200.00, 'fitid' => 'BB-0003', 'memo' => 'RECEBIMENTO CLIENTE DELTA'],
            ['type' => 'debit', 'date' => '20260811', 'value' => 1450.00, 'fitid' => 'BB-0004', 'memo' => 'MANUTENCAO DE MAQUINAS'],
        ]);

        $itauFile = $this->buildOfx('itau-agosto-2026.ofx', [
            ['type' => 'credit', 'date' => '20260808', 'value' => 8400.00, 'fitid' => 'ITAU-0001', 'memo' => 'CLIENTE BETA'],
            ['type' => 'debit', 'date' => '20260812', 'value' => 6750.00, 'fitid' => 'ITAU-0002', 'memo' => 'FORNECEDOR MATERIA-PRIMA'],
            ['type' => 'debit', 'date' => '20260813', 'value' => 1120.00, 'fitid' => 'ITAU-0003', 'memo' => 'ENERGIA ELETRICA'],
        ]);

        $cardFile = $this->buildInvoiceXlsx('fatura-mastercard-black-agosto-2026.xlsx', [
            ['date' => '2026-08-03', 'description' => 'Mercado Central', 'parcelamento' => '', 'value' => 420.50],
            ['date' => '2026-08-04', 'description' => 'Farmácia São João', 'parcelamento' => '', 'value' => 156.40],
            ['date' => '2026-08-05', 'description' => 'Posto Shell', 'parcelamento' => '', 'value' => 300.00],
            ['date' => '2026-08-06', 'description' => 'Loja de Materiais', 'parcelamento' => 'Parcela 1 de 3', 'value' => 890.00],
            ['date' => '2026-08-07', 'description' => 'Livraria', 'parcelamento' => '', 'value' => 72.80],
            ['date' => '2026-08-08', 'description' => 'Restaurante La Cucina', 'parcelamento' => '', 'value' => 180.90],
            ['date' => '2026-08-09', 'description' => 'Streaming', 'parcelamento' => '', 'value' => 39.90],
        ]);

        return [
            $dir.'/'.$bbFile,
            $dir.'/'.$itauFile,
            $dir.'/'.$cardFile,
        ];
    }

    /**
     * @param  list<array{type: string, date: string, value: float, fitid: string, memo: string}>  $transactions
     */
    private function buildOfx(string $filename, array $transactions): string
    {
        $blocks = '';

        foreach ($transactions as $tx) {
            $trntype = $tx['type'] === 'credit' ? 'CREDIT' : 'DEBIT';
            $amount = $tx['type'] === 'credit'
                ? number_format($tx['value'], 2, '.', '')
                : '-'.number_format($tx['value'], 2, '.', '');

            $blocks .= "          <STMTTRN>\n"
                ."            <TRNTYPE>{$trntype}</TRNTYPE>\n"
                ."            <DTPOSTED>{$tx['date']}</DTPOSTED>\n"
                ."            <TRNAMT>{$amount}</TRNAMT>\n"
                ."            <FITID>{$tx['fitid']}</FITID>\n"
                ."            <MEMO>{$tx['memo']}</MEMO>\n"
                ."          </STMTTRN>\n";
        }

        $content = "OFXHEADER:100\n"
            ."DATA:OFXSGML\n"
            ."VERSION:102\n"
            ."<OFX>\n"
            ."  <BANKMSGSRSV1>\n"
            ."    <STMTTRNRS>\n"
            ."      <STMTRS>\n"
            ."        <BANKTRANLIST>\n"
            .$blocks
            ."        </BANKTRANLIST>\n"
            ."      </STMTRS>\n"
            ."    </STMTTRNRS>\n"
            ."  </BANKMSGSRSV1>\n"
            ."</OFX>\n";

        file_put_contents(storage_path('app/demo/'.$filename), $content);

        return $filename;
    }

    /**
     * @param  list<array{date: string, description: string, parcelamento: string, value: float}>  $rows
     */
    private function buildInvoiceXlsx(string $filename, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Fatura Fechada - Agosto/2026'],
            ['Data', 'Lançamento', 'Parcelamento', 'Valor'],
        ], null);

        $total = 0.0;
        $rowIndex = 3;

        foreach ($rows as $row) {
            $serial = ExcelDate::dateTimeToExcel(new \DateTime($row['date']));
            $sheet->fromArray([
                [$serial, $row['description'], $row['parcelamento'], $row['value']],
            ], null, "A{$rowIndex}");

            $total += $row['value'];
            $rowIndex++;
        }

        $sheet->fromArray([
            ['', 'Pagamento Efetuado', '', -$total],
        ], null, "A{$rowIndex}");

        $writer = new Xlsx($spreadsheet);
        $writer->save(storage_path('app/demo/'.$filename));

        return $filename;
    }

    /**
     * @param  array<string, mixed>  $demo
     * @param  list<string>  $files
     */
    private function summary(array $demo, array $files): void
    {
        $this->command?->newLine();
        $this->command?->info('=== Demonstração agosto/2026 ===');
        $this->command?->info('Contas bancárias: '.$demo['bank_accounts']['bb']['name'].' e '.$demo['bank_accounts']['itau']['name']);
        $this->command?->info('Cartão: '.$demo['credit_card']['name']);
        $this->command?->info('Arquivos gerados (importar pela interface):');

        foreach ($files as $file) {
            $this->command?->info("  - {$file}");
        }
    }

    private function bankAccount(int $tid, string $name, string $bank, string $agency, string $account, float $initialBalance): string
    {
        return BankAccount::query()->create([
            'tenant_id' => $tid,
            'name' => $name,
            'bank' => $bank,
            'agency' => $agency,
            'account' => $account,
            'type' => 'checking',
            'initial_balance' => $initialBalance,
            'status' => 'active',
        ])->uuid;
    }

    private function costCenter(int $tid, string $name): string
    {
        return CostCenter::query()->create([
            'tenant_id' => $tid,
            'name' => $name,
            'status' => 'active',
        ])->uuid;
    }

    private function category(int $tid, string $name, string $type, string $color): string
    {
        return Category::query()->create([
            'tenant_id' => $tid,
            'name' => $name,
            'type' => $type,
            'color' => $color,
            'status' => 'active',
        ])->uuid;
    }

    private function open(int $tid, AccountType $type, string $description, string $counterparty, string $bankAccountId, string $categoryId, string $costCenterId, float $value, string $dueDate): void
    {
        FinancialAccount::query()->create([
            'tenant_id' => $tid,
            'type' => $type,
            'description' => $description,
            'counterparty' => $counterparty,
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => $value,
            'due_date' => $dueDate,
            'status' => AccountStatus::Open,
        ]);
    }

    private function settled(int $tid, ?int $userId, AccountType $type, string $description, string $counterparty, string $bankAccountId, string $categoryId, string $costCenterId, float $value, string $date): void
    {
        $account = FinancialAccount::query()->create([
            'tenant_id' => $tid,
            'type' => $type,
            'description' => $description,
            'counterparty' => $counterparty,
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => $value,
            'due_date' => $date,
            'paid_date' => $date,
            'status' => AccountStatus::Settled,
        ]);

        Settlement::query()->create([
            'tenant_id' => $tid,
            'account_id' => $account->getKey(),
            'value' => $value,
            'settled_at' => $date,
            'user_id' => $userId,
        ]);
    }

    private function cardPurchase(int $tid, string $cardId, string $description, string $categoryId, string $costCenterId, float $value, string $purchaseDate, string $dueDate): void
    {
        FinancialAccount::query()->create([
            'tenant_id' => $tid,
            'type' => AccountType::Payable,
            'description' => $description,
            'credit_card_id' => $cardId,
            'bank_account_id' => null,
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => $value,
            'purchase_date' => $purchaseDate,
            'due_date' => $dueDate,
            'status' => AccountStatus::Open,
            'is_card_purchase' => true,
            'is_card_invoice_payable' => false,
        ]);
    }
}
