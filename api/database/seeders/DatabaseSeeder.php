<?php

namespace Database\Seeders;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Auth\DTOs\NewTenantData;
use App\Modules\Auth\DTOs\NewUserData;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Category\Models\Category;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Tenant::query()->exists()) {
            return;
        }

        $result = app(AuthService::class)->register(
            new NewTenantData(
                name: 'Demo',
                document: '11222333000181',
                email: 'contato@demo.localhost',
                phone: '41999999999',
                domain: 'demo.localhost',
            ),
            new NewUserData(
                name: 'Administrador',
                email: 'admin@demo.localhost',
                phone: '41999999999',
                document: '52998224725',
                password: 'password',
            ),
        );

        $this->seedFinancialData($result->tenant);
    }

    private function seedFinancialData(Tenant $tenant): void
    {
        $tid = $tenant->getKey();

        $bb = $this->costCenter($tid, 'Banco do Brasil', '001', 50000);
        $itau = $this->costCenter($tid, 'Itaú', '341', 35000);
        $caixa = $this->costCenter($tid, 'Caixa Econômica', '104', 25000);

        $vendas = $this->category($tid, 'Vendas', 'income', '#10b981');
        $servicos = $this->category($tid, 'Serviços', 'income', '#14b8a6');
        $fornecedores = $this->category($tid, 'Fornecedores', 'expense', '#ef4444');
        $folha = $this->category($tid, 'Folha de Pagamento', 'expense', '#f97316');
        $impostos = $this->category($tid, 'Impostos', 'expense', '#8b5cf6');
        $infra = $this->category($tid, 'Infraestrutura', 'expense', '#64748b');

        // Histórico realizado — 8 meses de movimentações recorrentes
        for ($i = 7; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);

            $this->settledAccount($tid, AccountType::Receivable, "Vendas {$month->format('m/Y')}", 'Clientes diversos', $bb, $vendas, 28000 + ($i * 400), $month->copy()->day(5));
            $this->settledAccount($tid, AccountType::Receivable, "Serviços {$month->format('m/Y')}", 'Clientes recorrentes', $itau, $servicos, 15000 + ($i * 250), $month->copy()->day(10));
            $this->settledAccount($tid, AccountType::Receivable, "Vendas {$month->format('m/Y')}", 'Clientes diversos', $caixa, $vendas, 12000 + ($i * 200), $month->copy()->day(15));

            $this->settledAccount($tid, AccountType::Payable, 'Folha de pagamento', 'Colaboradores', $bb, $folha, 18000, $month->copy()->day(28));
            $this->settledAccount($tid, AccountType::Payable, 'Pagamento a fornecedores', 'Fornecedor principal', $itau, $fornecedores, 9000 + ($i * 150), $month->copy()->day(20));
            $this->settledAccount($tid, AccountType::Payable, 'Impostos e tributos', 'Receita Federal', $caixa, $impostos, 4500, $month->copy()->day(22));
            $this->settledAccount($tid, AccountType::Payable, 'Infraestrutura e TI', 'Provedores', $bb, $infra, 3200, $month->copy()->day(12));
        }

        // Lançamentos em aberto — vencidos
        $this->openAccount($tid, AccountType::Payable, 'Matéria-prima', 'Fornecedor Alfa', $itau, $fornecedores, 7500, now()->subDays(8));
        $this->openAccount($tid, AccountType::Payable, 'Aluguel do galpão', 'Imobiliária Central', $bb, $infra, 5200, now()->subDays(15));
        $this->openAccount($tid, AccountType::Payable, 'Conta de energia', 'Copel', $caixa, $infra, 1350, now()->subDays(3));

        // Lançamentos em aberto — a vencer
        $this->openAccount($tid, AccountType::Payable, 'Embalagens', 'Fornecedor Beta', $itau, $fornecedores, 4300, now()->addDays(5));
        $this->openAccount($tid, AccountType::Payable, 'DAS — Simples Nacional', 'Receita Federal', $caixa, $impostos, 6800, now()->addDays(12));
        $this->openAccount($tid, AccountType::Payable, 'Folha de pagamento', 'Colaboradores', $bb, $folha, 18000, now()->addDays(20));
        $this->openAccount($tid, AccountType::Receivable, 'Cliente Alfa', 'Alfa Comércio', $bb, $vendas, 16000, now()->addDays(7));
        $this->openAccount($tid, AccountType::Receivable, 'Cliente Beta', 'Beta Serviços', $itau, $servicos, 9200, now()->addDays(15));

        // Lançamento parcialmente pago
        $this->partialAccount($tid, AccountType::Receivable, 'Cliente Gama', 'Gama Ltda', $caixa, $vendas, 11000, 6000, now()->addDays(10));
    }

    private function costCenter(int $tid, string $name, string $agency, float $initialBalance): string
    {
        return CostCenter::query()->create([
            'tenant_id' => $tid,
            'name' => $name,
            'bank' => $name,
            'agency' => $agency,
            'account' => sprintf('%06d-%d', random_int(100000, 999999), random_int(0, 9)),
            'type' => 'checking',
            'initial_balance' => $initialBalance,
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

    private function settledAccount(int $tid, AccountType $type, string $description, string $counterparty, string $costCenterUuid, string $categoryUuid, float $value, Carbon $date): void
    {
        $account = FinancialAccount::query()->create([
            'tenant_id' => $tid,
            'type' => $type,
            'description' => $description,
            'counterparty' => $counterparty,
            'cost_center_id' => $costCenterUuid,
            'category_id' => $categoryUuid,
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
        ]);
    }

    private function openAccount(int $tid, AccountType $type, string $description, string $counterparty, string $costCenterUuid, string $categoryUuid, float $value, Carbon $dueDate): void
    {
        FinancialAccount::query()->create([
            'tenant_id' => $tid,
            'type' => $type,
            'description' => $description,
            'counterparty' => $counterparty,
            'cost_center_id' => $costCenterUuid,
            'category_id' => $categoryUuid,
            'value' => $value,
            'due_date' => $dueDate,
            'status' => AccountStatus::Open,
        ]);
    }

    private function partialAccount(int $tid, AccountType $type, string $description, string $counterparty, string $costCenterUuid, string $categoryUuid, float $value, float $settledValue, Carbon $dueDate): void
    {
        $account = FinancialAccount::query()->create([
            'tenant_id' => $tid,
            'type' => $type,
            'description' => $description,
            'counterparty' => $counterparty,
            'cost_center_id' => $costCenterUuid,
            'category_id' => $categoryUuid,
            'value' => $value,
            'due_date' => $dueDate,
            'status' => AccountStatus::Partial,
        ]);

        Settlement::query()->create([
            'tenant_id' => $tid,
            'account_id' => $account->getKey(),
            'value' => $settledValue,
            'settled_at' => now()->subDays(3),
        ]);
    }
}
