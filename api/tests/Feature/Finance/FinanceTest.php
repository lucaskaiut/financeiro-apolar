<?php

namespace Tests\Feature\Finance;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\CreditCard\Models\CreditCardInvoice;
use App\Modules\Report\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private function createBankAccount(): string
    {
        $response = $this->postJson('/api/bank-accounts', [
            'name' => 'Banco Principal',
            'bank' => 'Banco do Brasil',
            'agency' => '0001',
            'account' => '12345-6',
            'type' => 'checking',
            'initial_balance' => 1000,
            'status' => 'active',
        ])->assertCreated();

        return $response->json('data.id');
    }

    private function createCostCenter(string $name = 'Obra A'): string
    {
        return $this->postJson('/api/cost-centers', [
            'name' => $name,
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }

    private function createCompany(string $name = 'Imobiliária'): string
    {
        return $this->postJson('/api/companies', [
            'name' => $name,
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }

    private function createCategory(string $type = 'expense', string $name = 'Fornecedores'): string
    {
        $response = $this->postJson('/api/categories', [
            'name' => $name,
            'type' => $type,
            'color' => '#6366f1',
            'status' => 'active',
        ])->assertCreated();

        return $response->json('data.id');
    }

    public function test_cost_center_and_category_crud(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();

        $this->getJson('/api/bank-accounts')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/bank-accounts/{$costCenterId}")->assertOk()->assertJsonPath('data.name', 'Banco Principal');

        $this->putJson("/api/bank-accounts/{$costCenterId}", ['name' => 'Banco Renomeado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Banco Renomeado');

        $categoryId = $this->createCategory('income');

        $this->getJson('/api/categories?type=income')->assertOk()->assertJsonPath('meta.total', 1);
        $this->putJson("/api/categories/{$categoryId}", ['name' => 'Clientes'])->assertOk()->assertJsonPath('data.name', 'Clientes');

        $this->deleteJson("/api/categories/{$categoryId}")->assertOk();
        $this->deleteJson("/api/bank-accounts/{$costCenterId}")->assertOk();
    }

    public function test_account_create_and_installment_generation(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $response = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra de equipamentos',
            'counterparty' => 'Fornecedor X',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1200,
            'due_date' => '2026-01-10',
            'purchase_date' => '2026-01-10',
            'installments' => ['quantity' => 12, 'interval' => 'monthly'],
        ])->assertCreated();

        $this->assertCount(12, $response->json('data'));

        $accounts = FinancialAccount::query()->get();

        $this->assertCount(12, $accounts);
        $this->assertEqualsCanonicalizing(range(1, 12), $accounts->pluck('installment_number')->all());
        $this->assertEqualsCanonicalizing(array_fill(0, 12, 12), $accounts->pluck('installment_total')->all());
        $this->assertEqualsWithDelta(1200, $accounts->sum('value'), 0.01);

        $group = $accounts->first()->installment_group_id;
        $this->assertTrue($accounts->every(fn ($a) => $a->installment_group_id === $group));
    }

    public function test_account_due_date_ignores_timezone_offset(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $response = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Teste fuso',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => '2026-09-04T00:00:00.000Z',
            'purchase_date' => '2026-09-04T00:00:00.000Z',
            'installments' => ['quantity' => 3, 'interval' => 'monthly'],
        ])->assertCreated();

        $this->assertSame(
            ['2026-09-04', '2026-10-04', '2026-11-04'],
            collect($response->json('data'))->pluck('due_date')->all(),
        );
    }

    public function test_account_settle_partial_and_full(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Aluguel',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-02-01',
            'purchase_date' => '2026-02-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 400, 'settled_at' => '2026-02-01'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.settled_amount', 400)
            ->assertJsonPath('data.remaining_amount', 600)
            ->assertJsonPath('data.paid_date', '2026-02-01');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 600, 'settled_at' => '2026-02-01'])
            ->assertOk()
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.settled_amount', 1000)
            ->assertJsonPath('data.paid_date', '2026-02-01');

        $settlementId = $this->getJson("/api/accounts/{$accountId}")->json('data.settlements.0.id');

        $this->deleteJson("/api/accounts/{$accountId}/settlements/{$settlementId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'partial');
    }

    public function test_account_paid_date_can_be_updated(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Aluguel',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-02-01',
            'purchase_date' => '2026-02-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 1000, 'settled_at' => '2026-02-01'])
            ->assertOk()
            ->assertJsonPath('data.paid_date', '2026-02-01');

        $this->putJson("/api/accounts/{$accountId}", ['paid_date' => '2026-02-15'])
            ->assertOk()
            ->assertJsonPath('data.paid_date', '2026-02-15')
            ->assertJsonPath('data.settlements.0.settled_at', '2026-02-15');

        $this->putJson("/api/accounts/{$accountId}", ['paid_date' => null])
            ->assertUnprocessable();
    }

    public function test_accounts_can_be_filtered_by_paid_date(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountA = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta A',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => '2026-03-01',
            'purchase_date' => '2026-03-01',
        ])->json('data.0.id');

        $accountB = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta B',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 200,
            'due_date' => '2026-03-01',
            'purchase_date' => '2026-03-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountA}/settle", ['value' => 100, 'settled_at' => '2026-03-05'])->assertOk();
        $this->postJson("/api/accounts/{$accountB}/settle", ['value' => 200, 'settled_at' => '2026-03-20'])->assertOk();

        $this->getJson('/api/accounts?paid_from=2026-03-01&paid_to=2026-03-10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $accountA);

        $this->getJson('/api/accounts?paid_from=2026-03-15')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $accountB);
    }

    public function test_accounts_can_be_filtered_by_cost_center(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');
        $obraA = $this->createCostCenter('Obra A');
        $obraB = $this->createCostCenter('Obra B');

        $accountA = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Despesa obra A',
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $obraA,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => '2026-03-01',
            'purchase_date' => '2026-03-01',
        ])->json('data.0.id');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Despesa obra B',
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $obraB,
            'category_id' => $categoryId,
            'value' => 200,
            'due_date' => '2026-03-01',
            'purchase_date' => '2026-03-01',
        ])->assertCreated();

        $this->getJson("/api/accounts?cost_center_id={$obraA}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $accountA);
    }

    public function test_dashboard_can_be_filtered_by_cost_center(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');
        $obraA = $this->createCostCenter('Obra A');
        $obraB = $this->createCostCenter('Obra B');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Vencida obra A',
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $obraA,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => now()->subDays(3)->toDateString(),
            'purchase_date' => now()->subDays(3)->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Vencida obra B',
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $obraB,
            'category_id' => $categoryId,
            'value' => 250,
            'due_date' => now()->subDays(3)->toDateString(),
            'purchase_date' => now()->subDays(3)->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.payable_open', 350)
            ->assertJsonPath('data.kpis.overdue_count', 2)
            ->assertJsonCount(2, 'data.cost_centers');

        $this->getJson("/api/dashboard?cost_center_id={$obraA}")
            ->assertOk()
            ->assertJsonPath('data.selected_cost_center_id', $obraA)
            ->assertJsonPath('data.kpis.payable_open', 100)
            ->assertJsonPath('data.kpis.overdue_count', 1)
            ->assertJsonPath('data.overdue.0.description', 'Vencida obra A');
    }

    public function test_dashboard_can_be_filtered_by_bank_account(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bancoA = $this->createBankAccount();
        $bancoB = $this->postJson('/api/bank-accounts', [
            'name' => 'Banco Secundário',
            'bank' => 'Itaú',
            'agency' => '0002',
            'account' => '99999-9',
            'type' => 'checking',
            'initial_balance' => 0,
            'status' => 'active',
        ])->assertCreated()->json('data.id');
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Vencida banco A',
            'bank_account_id' => $bancoA,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => now()->subDays(3)->toDateString(),
            'purchase_date' => now()->subDays(3)->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Vencida banco B',
            'bank_account_id' => $bancoB,
            'category_id' => $categoryId,
            'value' => 250,
            'due_date' => now()->subDays(3)->toDateString(),
            'purchase_date' => now()->subDays(3)->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.payable_open', 350)
            ->assertJsonPath('data.kpis.overdue_count', 2)
            ->assertJsonCount(2, 'data.bank_accounts');

        $this->getJson("/api/dashboard?bank_account_id={$bancoA}")
            ->assertOk()
            ->assertJsonPath('data.selected_bank_account_id', $bancoA)
            ->assertJsonPath('data.kpis.payable_open', 100)
            ->assertJsonPath('data.kpis.overdue_count', 1)
            ->assertJsonPath('data.overdue.0.description', 'Vencida banco A')
            ->assertJsonCount(1, 'data.balance_by_bank_account')
            ->assertJsonPath('data.balance_by_bank_account.0.bank_account_id', $bancoA);
    }

    public function test_accounts_overdue_filter_returns_unpaid_past_due(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $overdueId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta vencida',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 150,
            'due_date' => now()->subDays(5)->toDateString(),
            'purchase_date' => now()->subDays(5)->toDateString(),
        ])->json('data.0.id');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta futura',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 250,
            'due_date' => now()->addDays(5)->toDateString(),
            'purchase_date' => now()->addDays(5)->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/accounts?overdue=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $overdueId);
    }

    public function test_settled_account_value_syncs_to_settlement_and_cash_flow(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('income');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'receivable',
            'description' => 'Venda',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => '2026-05-01',
            'purchase_date' => '2026-05-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 100, 'settled_at' => '2026-05-10'])->assertOk();

        $this->putJson("/api/accounts/{$accountId}", ['value' => 200])
            ->assertOk()
            ->assertJsonPath('data.value', 200)
            ->assertJsonPath('data.settlements.0.value', 200);

        $realized = $this->getJson('/api/cash-flow/realized?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->json('data');

        $this->assertEquals(200, $realized['total_in']);
    }

    public function test_multiple_settlements_adjust_last_value_on_account_update(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Fornecedor',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-06-01',
            'purchase_date' => '2026-06-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 400, 'settled_at' => '2026-06-01'])->assertOk();
        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 600, 'settled_at' => '2026-06-05'])->assertOk();

        $this->putJson("/api/accounts/{$accountId}", ['value' => 1100])
            ->assertOk()
            ->assertJsonPath('data.settlements.0.value', 400)
            ->assertJsonPath('data.settlements.1.value', 700);

        $this->putJson("/api/accounts/{$accountId}", ['value' => 350])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_settled_account_type_change_reflects_in_cash_flow(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Aluguel',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 500,
            'due_date' => '2026-07-01',
            'purchase_date' => '2026-07-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 500, 'settled_at' => '2026-07-05'])->assertOk();

        $realizedBefore = $this->getJson('/api/cash-flow/realized?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->json('data');

        $this->assertEquals(500, $realizedBefore['total_out']);
        $this->assertEquals(0, $realizedBefore['total_in']);

        $this->putJson("/api/accounts/{$accountId}", ['type' => 'receivable'])
            ->assertOk()
            ->assertJsonPath('data.type', 'receivable');

        $realizedAfter = $this->getJson('/api/cash-flow/realized?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $realizedAfter['total_out']);
        $this->assertEquals(500, $realizedAfter['total_in']);
    }

    public function test_account_with_settlements_cannot_be_deleted(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Fatura',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 500,
            'due_date' => '2026-03-01',
            'purchase_date' => '2026-03-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 500])->assertOk();

        $this->deleteJson("/api/accounts/{$accountId}")->assertUnprocessable();
    }

    public function test_settled_account_can_be_reopened(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Energia',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 500,
            'due_date' => '2026-03-01',
            'purchase_date' => '2026-03-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 500, 'settled_at' => '2026-03-05'])->assertOk();

        $this->postJson("/api/accounts/{$accountId}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.settled_amount', 0)
            ->assertJsonPath('data.remaining_amount', 500)
            ->assertJsonPath('data.paid_date', null)
            ->assertJsonPath('data.is_reconciled', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.reopen',
            'entity_type' => 'account',
            'entity_id' => $accountId,
        ]);
    }

    public function test_recurrence_generates_future_occurrences(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/recurrences', [
            'type' => 'payable',
            'description' => 'Internet',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 200,
            'frequency' => 'monthly',
            'start_date' => '2026-01-10',
            'day_of_month' => 10,
            'max_occurrences' => 12,
        ])->assertCreated();

        $accounts = FinancialAccount::query()->get();

        $this->assertCount(12, $accounts);
        $this->assertTrue($accounts->every(fn ($a) => $a->recurrence_id !== null));
        $this->assertTrue($accounts->every(fn ($a) => $a->due_date->day === 10));
    }

    public function test_transfer_creates_two_movements(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $fromId = $this->createBankAccount();
        $toId = $this->postJson('/api/bank-accounts', [
            'name' => 'Banco B',
            'type' => 'checking',
            'initial_balance' => 0,
            'status' => 'active',
        ])->json('data.id');

        $this->postJson('/api/transfers', [
            'from_bank_account_id' => $fromId,
            'to_bank_account_id' => $toId,
            'value' => 5000,
            'date' => '2026-01-15',
        ])->assertCreated();

        $accounts = FinancialAccount::query()->get();

        $this->assertCount(2, $accounts);
        $this->assertTrue($accounts->every(fn ($a) => $a->transfer_id !== null));
        $this->assertTrue($accounts->every(fn ($a) => $a->status->value === 'settled'));
    }

    public function test_cash_flow_realized_and_projected(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'receivable',
            'description' => 'Venda',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 800,
            'due_date' => '2026-04-01',
            'purchase_date' => '2026-04-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 800, 'settled_at' => '2026-04-01'])->assertOk();

        $realized = $this->getJson('/api/cash-flow/realized?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->json('data');

        $this->assertEquals(800, $realized['total_in']);

        $this->putJson("/api/accounts/{$accountId}", ['value' => 950])->assertOk();

        $realized = $this->getJson('/api/cash-flow/realized?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->json('data');

        $this->assertEquals(950, $realized['total_in']);
        $this->assertEquals(1000, $realized['opening_balance']);
        $this->assertEquals(1950, $realized['final_balance']);

        $projected = $this->getJson('/api/cash-flow/projected?days=30')->assertOk()->json('data');

        $this->assertArrayHasKey('series', $projected);
        $this->assertArrayHasKey('accounts', $projected);
    }

    public function test_reconciliation_ofx_import_auto_match_and_undo(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Internet',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 200,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated();

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260810</DTPOSTED>
            <TRNAMT>-200.00</TRNAMT>
            <FITID>FIT-001</FITID>
            <MEMO>INTERNET</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $costCenterId,
            'content' => $ofx,
        ])->assertOk()->assertJsonPath('data.imported', 1);

        $this->getJson('/api/reconciliation/transactions')->assertOk()->assertJsonPath('meta.total', 1);

        $transactionId = $this->getJson('/api/reconciliation/transactions')->json('data.0.id');

        $candidates = $this->getJson("/api/reconciliation/transactions/{$transactionId}/candidates")->json('data.candidates');

        $this->assertCount(1, $candidates);

        $result = $this->postJson('/api/reconciliation/auto')->assertOk()->json('data');

        $this->assertEquals(1, $result['matched']);

        $this->getJson("/api/reconciliation/transactions/{$transactionId}")
            ->assertNotFound();

        $this->getJson('/api/reconciliation/transactions?status=matched')->assertOk()->assertJsonPath('meta.total', 1);

        $this->postJson("/api/reconciliation/transactions/{$transactionId}/undo")->assertOk();

        $this->getJson('/api/reconciliation/transactions?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_reconciliation_identify_returns_linked_accounts(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Internet',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 200,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated();

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260810</DTPOSTED>
            <TRNAMT>-200.00</TRNAMT>
            <FITID>FIT-IDENT-001</FITID>
            <MEMO>INTERNET</MEMO>
          </STMTTRN>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260811</DTPOSTED>
            <TRNAMT>-500.00</TRNAMT>
            <FITID>FIT-IDENT-002</FITID>
            <MEMO>MANUTENCAO</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $bankAccountId,
            'content' => $ofx,
        ])->assertOk()->assertJsonPath('data.imported', 2);

        $transactions = collect($this->getJson('/api/reconciliation/transactions?status=pending')->assertOk()->json('data'))
            ->keyBy('description');

        $identified = $this->getJson('/api/reconciliation/identify')->assertOk()->json('data');

        $this->assertCount(2, $identified);

        $internetTx = $transactions['INTERNET'];
        $manutTx = $transactions['MANUTENCAO'];

        $this->assertCount(1, $identified[$internetTx['id']]);
        $this->assertEquals('Internet', $identified[$internetTx['id']][0]['description']);

        $this->assertCount(0, $identified[$manutTx['id']]);
    }

    public function test_reconciliation_spreadsheet_import(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();

        $path = $this->buildBradescoStatement();

        $file = new UploadedFile($path, 'Bradesco_10092026_192443.XLS', 'application/vnd.ms-excel', null, true);

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $bankAccountId,
            'file' => $file,
        ])->assertOk()->assertJsonPath('data.imported', 3);

        $transactions = $this->getJson('/api/reconciliation/transactions?status=pending')->assertOk();

        $transactions->assertJsonPath('meta.total', 3);

        $data = collect($transactions->json('data'));

        $credit = $data->firstWhere('type', 'credit');
        $this->assertEquals('RENTAB.INVEST FACILCRED*', $credit['description']);
        $this->assertEquals(0.33, $credit['value']);

        $zap = $data->firstWhere('description', 'PAGTO ELETRON COBRANCA ZAP');
        $this->assertEquals('debit', $zap['type']);
        $this->assertEquals(2325.93, $zap['value']);

        $pix = $data->firstWhere('description', 'PIX ENVIADO DES: FULANO');
        $this->assertEquals('debit', $pix['type']);
        $this->assertEquals(2000.0, $pix['value']);
    }

    private function buildBradescoStatement(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['', 'Bradesco Net Empresa', '', '', '', ''],
            ['Data', 'Lançamento', 'Dcto.', 'Crédito (R$)', 'Débito (R$)', 'Saldo (R$)'],
            ['01/09/2026', 'SALDO ANTERIOR', '', '', '', '32.381,23'],
            ['09/09/2026', 'RENTAB.INVEST FACILCRED*', '2130727', '0,33', '', '32.381,56'],
            ['09/09/2026', 'PAGTO ELETRON COBRANCA ZAP', '909', '', '-2.325,93', '30.055,79'],
            ['10/09/2026', 'PIX ENVIADO DES: FULANO', '1626180', '', '-2.000,00', '27.816,88'],
            ['Total', '', '', '0,33', '-4.325,93', ''],
        ], null);

        $writer = new Xls($spreadsheet);
        $path = tempnam(sys_get_temp_dir(), 'extrato_').'.xls';
        $writer->save($path);

        return $path;
    }

    public function test_credit_card_invoice_import_full_flow(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $cardId = $this->postJson('/api/credit-cards', [
            'name' => 'Mastercard Black',
            'institution' => 'Itaú',
            'closing_day' => 10,
            'due_day' => 15,
            'bank_account_id' => $bankAccountId,
        ])->assertCreated()->json('data.id');

        $path = $this->buildCardStatement();

        $file = new UploadedFile($path, 'fatura.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->postJson("/api/credit-cards/{$cardId}/invoices/import", [
            'file' => $file,
            'reference_month' => '2026-09',
            'paid_date' => '2026-09-15',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'cost_center_id' => $costCenterId,
        ])->assertOk();

        $response->assertJsonPath('data.imported', 2);
        $response->assertJsonPath('data.skipped', 1);
        $response->assertJsonPath('data.total', 300.5);

        $invoice = CreditCardInvoice::query()->first();

        $this->assertNotNull($invoice);
        $this->assertEquals('2026-09', $invoice->reference_month);
        $this->assertEquals('paid', $invoice->status->value);
        $this->assertEquals(300.5, (float) $invoice->total_value);

        $payable = FinancialAccount::query()->find($invoice->financial_account_id);
        $this->assertEquals(AccountStatus::Settled, $payable->status);
        $this->assertEquals($bankAccountId, $payable->bank_account_id);

        $purchases = FinancialAccount::query()
            ->where('credit_card_invoice_id', $invoice->uuid)
            ->get();

        $this->assertCount(2, $purchases);
        $this->assertTrue($purchases->every(fn (FinancialAccount $purchase) => $purchase->status === AccountStatus::Settled));
        $this->assertTrue($purchases->every(fn (FinancialAccount $purchase) => $purchase->category_id === $categoryId));
        $this->assertTrue($purchases->every(fn (FinancialAccount $purchase) => $purchase->cost_center_id === $costCenterId));

        $this->getJson("/api/credit-cards/{$cardId}/invoices")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    }

    public function test_credit_card_invoice_import_aborts_when_invoice_exists(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $cardId = $this->postJson('/api/credit-cards', [
            'name' => 'Visa Gold',
            'closing_day' => 10,
            'due_day' => 15,
            'bank_account_id' => $bankAccountId,
        ])->assertCreated()->json('data.id');

        $path = $this->buildCardStatement();
        $file = new UploadedFile($path, 'fatura.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $payload = [
            'file' => $file,
            'reference_month' => '2026-09',
            'paid_date' => '2026-09-15',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
        ];

        $this->postJson("/api/credit-cards/{$cardId}/invoices/import", $payload)->assertOk();

        $secondFile = new UploadedFile($path, 'fatura.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $payload['file'] = $secondFile;

        $this->postJson("/api/credit-cards/{$cardId}/invoices/import", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference_month');
    }

    private function buildCardStatement(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $dateA = ExcelDate::dateTimeToExcel(new \DateTime('2026-08-28'));
        $dateB = ExcelDate::dateTimeToExcel(new \DateTime('2026-08-27'));

        $sheet->fromArray([
            ['Fatura Fechada - Setembro/2026'],
            ['Data', 'Lançamento', 'Parcelamento', 'Valor'],
            [$dateA, 'Compra A', '', 100.5],
            [$dateB, 'Compra B', 'Parcela 1 de 3', 200],
            [$dateB, 'Compra B', 'Parcela 2 de 3', 200],
            [$dateA, 'Pagamento Efetuado', '', -500],
        ], null);

        $writer = new Xlsx($spreadsheet);
        $path = tempnam(sys_get_temp_dir(), 'fatura_').'.xlsx';
        $writer->save($path);

        return $path;
    }

    public function test_reconciliation_one_transaction_to_many_accounts_and_undo(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $firstId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Fornecedor A',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 300,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated()->json('data.0.id');

        $secondId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Fornecedor B',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 700,
            'due_date' => '2026-08-12',
            'purchase_date' => '2026-08-12',
        ])->assertCreated()->json('data.0.id');

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260815</DTPOSTED>
            <TRNAMT>-1000.00</TRNAMT>
            <FITID>FIT-MANY-001</FITID>
            <MEMO>PAGAMENTO AGRUPADO</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $bankAccountId,
            'content' => $ofx,
        ])->assertOk()->assertJsonPath('data.imported', 1);

        $transactionId = $this->getJson('/api/reconciliation/transactions?status=pending')
            ->assertOk()
            ->json('data.0.id');

        $candidates = $this->getJson("/api/reconciliation/transactions/{$transactionId}/candidates?exact=0")
            ->assertOk()
            ->json('data.candidates');

        $this->assertCount(2, $candidates);

        $this->postJson('/api/reconciliation/reconcile-many', [
            'transactions' => [$transactionId],
            'accounts' => [$firstId, $secondId],
        ])->assertOk();

        $this->getJson('/api/accounts/'.$firstId)->assertOk()->assertJsonPath('data.status', 'settled');
        $this->getJson('/api/accounts/'.$secondId)->assertOk()->assertJsonPath('data.status', 'settled');

        $matched = $this->getJson('/api/reconciliation/transactions?status=matched')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->json('data.0');

        $this->assertCount(2, $matched['matched_accounts']);

        $this->postJson("/api/reconciliation/transactions/{$transactionId}/undo")->assertOk();

        $this->getJson('/api/accounts/'.$firstId)->assertOk()->assertJsonPath('data.status', 'open');
        $this->getJson('/api/accounts/'.$secondId)->assertOk()->assertJsonPath('data.status', 'open');
        $this->getJson('/api/reconciliation/transactions?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_reconciliation_updates_account_bank_when_different(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $statementBankId = $this->createBankAccount();
        $otherBankId = $this->postJson('/api/bank-accounts', [
            'name' => 'Banco Secundário',
            'bank' => 'Itaú',
            'agency' => '0002',
            'account' => '99999-0',
            'type' => 'checking',
            'initial_balance' => 500,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta em outro banco',
            'bank_account_id' => $otherBankId,
            'category_id' => $categoryId,
            'value' => 150,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated()->json('data.0.id');

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260810</DTPOSTED>
            <TRNAMT>-150.00</TRNAMT>
            <FITID>FIT-BANK-CHANGE</FITID>
            <MEMO>PAGAMENTO</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $statementBankId,
            'content' => $ofx,
        ])->assertOk();

        $transactionId = $this->getJson('/api/reconciliation/transactions?status=pending')
            ->assertOk()
            ->json('data.0.id');

        $candidates = $this->getJson("/api/reconciliation/transactions/{$transactionId}/candidates?exact=0")
            ->assertOk()
            ->json('data.candidates');

        $this->assertTrue(collect($candidates)->contains(fn (array $item) => $item['id'] === $accountId));

        $this->postJson('/api/reconciliation/reconcile-many', [
            'transactions' => [$transactionId],
            'accounts' => [$accountId],
        ])->assertOk();

        $this->getJson('/api/accounts/'.$accountId)
            ->assertOk()
            ->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.bank_account_id', $statementBankId);
    }

    public function test_reconciliation_create_account_with_selected_accounts_settles_all(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $firstId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Parcela 1',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 200,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated()->json('data.0.id');

        $secondId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Parcela 2',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 300,
            'due_date' => '2026-08-11',
            'purchase_date' => '2026-08-11',
        ])->assertCreated()->json('data.0.id');

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260815</DTPOSTED>
            <TRNAMT>-1000.00</TRNAMT>
            <FITID>FIT-CREATE-MANY</FITID>
            <MEMO>PAGAMENTO TOTAL</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $bankAccountId,
            'content' => $ofx,
        ])->assertOk();

        $transactionId = $this->getJson('/api/reconciliation/transactions?status=pending')
            ->assertOk()
            ->json('data.0.id');

        $createdId = $this->postJson("/api/reconciliation/transactions/{$transactionId}/create-account", [
            'type' => 'payable',
            'description' => 'Complemento',
            'category_id' => $categoryId,
            'bank_account_id' => $bankAccountId,
            'value' => 500,
            'due_date' => '2026-08-15',
            'account_ids' => [$firstId, $secondId],
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/accounts/'.$firstId)->assertOk()->assertJsonPath('data.status', 'settled');
        $this->getJson('/api/accounts/'.$secondId)->assertOk()->assertJsonPath('data.status', 'settled');
        $this->getJson('/api/accounts/'.$createdId)->assertOk()->assertJsonPath('data.status', 'settled');
        $this->getJson('/api/reconciliation/transactions?status=matched')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_payables_report_treats_unselected_due_today_as_overdue(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        try {
            $tenant = $this->createTenantWithRoles();
            Sanctum::actingAs($this->createAdmin($tenant));

            $costCenterId = $this->createBankAccount();
            $categoryId = $this->createCategory('expense');

            $dueTodayId = $this->postJson('/api/accounts', [
                'type' => 'payable',
                'description' => 'Conta do dia',
                'bank_account_id' => $costCenterId,
                'category_id' => $categoryId,
                'value' => 500,
                'due_date' => '2026-09-01',
                'purchase_date' => '2026-09-01',
            ])->json('data.0.id');

            $response = $this->getJson('/api/reports/payables')->assertOk();

            $this->assertEquals(500, $response->json('data.total_overdue'));

            $account = collect($response->json('data.accounts'))->firstWhere('id', $dueTodayId);
            $this->assertTrue($account['is_due_today']);
            $this->assertFalse($account['is_overdue']);

            $service = app(ReportService::class);
            $data = $service->payables();
            $method = new \ReflectionMethod($service, 'buildPayablesExportGroups');
            $method->setAccessible(true);

            $unselectedGroups = $method->invoke($service, $data['accounts'], [], $data['reference_date']);
            $this->assertCount(1, $unselectedGroups[0]['overdue']['accounts']);
            $this->assertSame($dueTodayId, $unselectedGroups[0]['overdue']['accounts'][0]['id']);
            $this->assertCount(0, $unselectedGroups[0]['due_today']['accounts']);

            $selectedGroups = $method->invoke($service, $data['accounts'], [$dueTodayId], $data['reference_date']);
            $this->assertCount(0, $selectedGroups[0]['overdue']['accounts']);
            $this->assertCount(1, $selectedGroups[0]['due_today']['accounts']);
            $this->assertSame($dueTodayId, $selectedGroups[0]['due_today']['accounts'][0]['id']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reports_endpoints_respond(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/reports/daily')->assertOk();
        $this->getJson('/api/reports/weekly')->assertOk();
        $this->getJson('/api/reports/provision?days=30')->assertOk();
        $this->getJson('/api/reports/by-category')->assertOk();
        $this->getJson('/api/reports/monthly-summary')->assertOk();
        $this->getJson('/api/reports/by-cost-center')->assertOk();
        $this->getJson('/api/reports/by-bank-account')->assertOk();
        $this->getJson('/api/reports/cash-flow')->assertOk();
        $this->getJson('/api/reports/payables')->assertOk();
    }

    public function test_monthly_summary_groups_expenses_by_cost_center(): void
    {
        Carbon::setTestNow('2026-03-15');

        try {
            $tenant = $this->createTenantWithRoles();
            Sanctum::actingAs($this->createAdmin($tenant));

            $bankAccountId = $this->createBankAccount();
            $categoryId = $this->createCategory('expense');
            $obraA = $this->createCostCenter('Obra A');
            $obraB = $this->createCostCenter('Obra B');

            $accountA = $this->postJson('/api/accounts', [
                'type' => 'payable',
                'description' => 'Despesa obra A',
                'bank_account_id' => $bankAccountId,
                'cost_center_id' => $obraA,
                'category_id' => $categoryId,
                'value' => 100,
                'due_date' => '2026-03-01',
                'purchase_date' => '2026-03-01',
            ])->assertCreated()->json('data.0.id');

            $accountB = $this->postJson('/api/accounts', [
                'type' => 'payable',
                'description' => 'Despesa obra B',
                'bank_account_id' => $bankAccountId,
                'cost_center_id' => $obraB,
                'category_id' => $categoryId,
                'value' => 250,
                'due_date' => '2026-03-01',
                'purchase_date' => '2026-03-01',
            ])->assertCreated()->json('data.0.id');

            $this->postJson("/api/accounts/{$accountA}/settle", [
                'value' => 100,
                'settled_at' => '2026-03-10',
            ])->assertOk();

            $this->postJson("/api/accounts/{$accountB}/settle", [
                'value' => 250,
                'settled_at' => '2026-03-12',
            ])->assertOk();

            $summary = $this->getJson('/api/reports/monthly-summary?from=2026-03-01&to=2026-03-31')
                ->assertOk()
                ->json('data');

            $rows = collect($summary['rows']);

            $this->assertEqualsWithDelta(350, $summary['grand_total']['total'], 0.01);
            $this->assertEqualsWithDelta(100, $rows->firstWhere('bank_account', 'Obra A')['total'], 0.01);
            $this->assertEqualsWithDelta(250, $rows->firstWhere('bank_account', 'Obra B')['total'], 0.01);
            $this->assertNull($rows->firstWhere('bank_account', 'Banco Principal'));

            $this->getJson('/api/reports/by-bank-account')
                ->assertOk()
                ->assertJsonPath('data.rows.0.bank_account', 'Banco Principal')
                ->assertJsonPath('data.rows.0.expense', 350);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_account_documents_upload_list_preview_and_delete(): void
    {
        Storage::fake('local');

        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra com anexos',
            'bank_account_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 300,
            'due_date' => '2026-09-01',
            'purchase_date' => '2026-09-01',
        ])->json('data.0.id');

        $pdf = UploadedFile::fake()->create('fatura.pdf', 2048, 'application/pdf');
        $image = UploadedFile::fake()->create('nota.png', 1024, 'image/png');

        $this->post("/api/accounts/{$accountId}/documents", [
            'files' => [$pdf, $image],
        ])->assertCreated()->assertJsonCount(2, 'data');

        $documents = $this->getJson("/api/accounts/{$accountId}/documents")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $firstId = $documents[0]['id'];

        $this->get("/api/accounts/{$accountId}/documents/{$firstId}/download")->assertOk();
        $this->get("/api/accounts/{$accountId}/documents/{$firstId}/download?download=1")->assertOk();

        $this->deleteJson("/api/accounts/{$accountId}/documents/{$firstId}")->assertOk();

        $this->getJson("/api/accounts/{$accountId}/documents")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_credit_card_invoice_payment_does_not_double_count_expenses(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');
        $obraA = $this->createCostCenter('Obra A');

        $cardId = $this->postJson('/api/credit-cards', [
            'name' => 'Cartão Itaú',
            'institution' => 'Itaú',
            'closing_day' => 25,
            'due_day' => 5,
            'bank_account_id' => $bankAccountId,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra A',
            'category_id' => $categoryId,
            'cost_center_id' => $obraA,
            'credit_card_id' => $cardId,
            'value' => 1000,
            'purchase_date' => '2026-09-01',
        ])->assertCreated();

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra B',
            'category_id' => $categoryId,
            'cost_center_id' => $obraA,
            'credit_card_id' => $cardId,
            'value' => 500,
            'purchase_date' => '2026-09-02',
        ])->assertCreated();

        $invoice = $this->postJson("/api/credit-cards/{$cardId}/invoices/close", [
            'reference_month' => '2026-09',
        ])->assertOk()->json('data');

        $this->assertEqualsWithDelta(1500, $invoice['total_value'], 0.01);

        $purchases = FinancialAccount::query()->where('is_card_purchase', true)->get();
        $this->assertCount(2, $purchases);
        $this->assertTrue($purchases->every(fn (FinancialAccount $account) => $account->status === AccountStatus::Open));
        $this->assertTrue($purchases->every(fn (FinancialAccount $account) => $account->credit_card_invoice_id !== null));
        $this->assertTrue($purchases->every(fn (FinancialAccount $account) => $account->cost_center_id !== null));

        $invoicePayable = FinancialAccount::query()->where('is_card_invoice_payable', true)->first();
        $this->assertNotNull($invoicePayable);
        $this->assertEqualsWithDelta(1500, $invoicePayable->value, 0.01);
        $this->assertSame(AccountStatus::Open, $invoicePayable->status);

        $this->postJson("/api/accounts/{$invoicePayable->uuid}/settle", [
            'value' => 1500,
            'settled_at' => '2026-10-05',
        ])->assertOk();

        $purchases->each->refresh();
        $this->assertTrue($purchases->every(fn (FinancialAccount $account) => $account->status === AccountStatus::Settled));

        $cashFlow = $this->getJson('/api/cash-flow/realized?from=2026-10-01&to=2026-10-31')->assertOk()->json('data');
        $this->assertEqualsWithDelta(1500, $cashFlow['total_out'], 0.01);
        $this->assertCount(2, $cashFlow['entries']);
        $this->assertTrue(collect($cashFlow['entries'])->every(
            fn (array $entry) => ! str_contains($entry['description'], 'Fatura'),
        ));

        // Em todos os totais: compras (1500), nunca fatura + compras (3000) nem só a fatura agregada.
        $daily = $this->getJson('/api/reports/daily?date=2026-10-05')->assertOk()->json('data');
        $this->assertEqualsWithDelta(1500, $daily['total_paid'], 0.01);
        $this->assertCount(2, $daily['payments']);
        $this->assertTrue(collect($daily['payments'])->every(
            fn (array $payment) => ! str_contains($payment['description'], 'Fatura'),
        ));

        $weekly = $this->getJson('/api/reports/weekly?from=2026-10-01&to=2026-10-31')->assertOk()->json('data');
        $this->assertEqualsWithDelta(1500, $weekly['total_paid'], 0.01);

        $byCategory = $this->getJson('/api/reports/by-category?from=2026-10-01&to=2026-10-31')->assertOk()->json('data');
        $expenseTotal = collect($byCategory['expense'])->sum('total');
        $this->assertEqualsWithDelta(1500, $expenseTotal, 0.01);

        $balanceRows = $this->getJson('/api/reports/by-cost-center')->assertOk()->json('data.rows');
        $bankRow = collect($balanceRows)->firstWhere('bank_account_id', $bankAccountId);
        $this->assertNotNull($bankRow);
        $this->assertEqualsWithDelta(1500, $bankRow['expense'], 0.01);

        $filteredCash = $this->getJson("/api/cash-flow/realized?from=2026-10-01&to=2026-10-31&bank_account_id={$bankAccountId}")
            ->assertOk()
            ->json('data');
        $this->assertEqualsWithDelta(1500, $filteredCash['total_out'], 0.01);
    }

    public function test_credit_card_purchase_supports_installments(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');
        $obraA = $this->createCostCenter('Obra A');

        $cardId = $this->postJson('/api/credit-cards', [
            'name' => 'Cartão Visa',
            'institution' => 'Visa',
            'closing_day' => 10,
            'due_day' => 17,
            'bank_account_id' => $bankAccountId,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $response = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Notebook',
            'category_id' => $categoryId,
            'cost_center_id' => $obraA,
            'credit_card_id' => $cardId,
            'value' => 3000,
            'purchase_date' => '2026-09-04',
            'installments' => ['quantity' => 3],
        ])->assertCreated();

        $this->assertCount(3, $response->json('data'));
        $this->assertTrue(collect($response->json('data'))->every(fn ($row) => $row['is_card_purchase'] === true));

        $purchases = FinancialAccount::query()
            ->where('is_card_purchase', true)
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $purchases);
        $this->assertEqualsCanonicalizing([1, 2, 3], $purchases->pluck('installment_number')->all());
        $this->assertTrue($purchases->every(fn (FinancialAccount $account) => $account->installment_total === 3));
        $this->assertEqualsWithDelta(3000, $purchases->sum('value'), 0.01);
        $this->assertSame(['2026-09-04', '2026-09-04', '2026-09-04'], $purchases->map(fn ($a) => $a->purchase_date->toDateString())->all());
        // Fecha dia 10, vence dia 17 (mesmo mês do fechamento); parcelas avançam só o vencimento
        $this->assertSame(['2026-09-17', '2026-10-17', '2026-11-17'], $purchases->map(fn ($a) => $a->due_date->toDateString())->all());
        $this->assertSame('Notebook (1/3)', $purchases[0]->description);

        $september = $this->postJson("/api/credit-cards/{$cardId}/invoices/close", [
            'reference_month' => '2026-09',
        ])->assertOk()->json('data');

        $this->assertEqualsWithDelta(1000, $september['total_value'], 0.01);
        $this->assertSame('2026-09-17', $september['due_date']);
        $this->assertSame(1, FinancialAccount::query()->where('is_card_purchase', true)->whereNotNull('credit_card_invoice_id')->count());
        $this->assertSame(2, FinancialAccount::query()->where('is_card_purchase', true)->whereNull('credit_card_invoice_id')->count());
        $this->assertSame(
            AccountStatus::Open,
            FinancialAccount::query()->where('is_card_purchase', true)->whereNotNull('credit_card_invoice_id')->value('status'),
        );
    }

    public function test_unsettle_of_reconciled_settlement_reverses_bank_link(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta conciliada',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 250,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated()->json('data.0.id');

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260815</DTPOSTED>
            <TRNAMT>-250.00</TRNAMT>
            <FITID>FIT-UNSETTLE-001</FITID>
            <MEMO>PAGAMENTO</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $bankAccountId,
            'content' => $ofx,
        ])->assertOk();

        $transactionId = $this->getJson('/api/reconciliation/transactions?status=pending')
            ->assertOk()
            ->json('data.0.id');

        $this->postJson("/api/reconciliation/transactions/{$transactionId}/reconcile", [
            'account_id' => $accountId,
        ])->assertOk();

        $settlementId = $this->getJson("/api/accounts/{$accountId}")
            ->assertOk()
            ->assertJsonPath('data.is_reconciled', true)
            ->json('data.settlements.0.id');

        $this->deleteJson("/api/accounts/{$accountId}/settlements/{$settlementId}")->assertOk();

        $this->getJson("/api/accounts/{$accountId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.is_reconciled', false)
            ->assertJsonPath('data.settled_amount', 0);

        $this->getJson('/api/reconciliation/transactions?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_reopen_of_one_account_in_one_to_many_reopens_all_linked_accounts(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $firstId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Parte A',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 300,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated()->json('data.0.id');

        $secondId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Parte B',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 700,
            'due_date' => '2026-08-12',
            'purchase_date' => '2026-08-12',
        ])->assertCreated()->json('data.0.id');

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260815</DTPOSTED>
            <TRNAMT>-1000.00</TRNAMT>
            <FITID>FIT-REOPEN-MANY-001</FITID>
            <MEMO>PAGAMENTO AGRUPADO</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $bankAccountId,
            'content' => $ofx,
        ])->assertOk();

        $transactionId = $this->getJson('/api/reconciliation/transactions?status=pending')
            ->assertOk()
            ->json('data.0.id');

        $this->postJson('/api/reconciliation/reconcile-many', [
            'transactions' => [$transactionId],
            'accounts' => [$firstId, $secondId],
        ])->assertOk();

        $this->postJson("/api/accounts/{$firstId}/reopen")->assertOk();

        $this->getJson("/api/accounts/{$firstId}")->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.is_reconciled', false);
        $this->getJson("/api/accounts/{$secondId}")->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.is_reconciled', false);
        $this->getJson('/api/reconciliation/transactions?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_reconcile_many_rejects_nxn_pairs_with_mismatched_values(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $firstId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta 80',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 80,
            'due_date' => '2026-08-10',
            'purchase_date' => '2026-08-10',
        ])->assertCreated()->json('data.0.id');

        $secondId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta 70',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 70,
            'due_date' => '2026-08-12',
            'purchase_date' => '2026-08-12',
        ])->assertCreated()->json('data.0.id');

        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260815</DTPOSTED>
            <TRNAMT>-100.00</TRNAMT>
            <FITID>FIT-NXN-100</FITID>
            <MEMO>EXTRATO 100</MEMO>
          </STMTTRN>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260816</DTPOSTED>
            <TRNAMT>-50.00</TRNAMT>
            <FITID>FIT-NXN-50</FITID>
            <MEMO>EXTRATO 50</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $this->postJson('/api/reconciliation/import', [
            'bank_account_id' => $bankAccountId,
            'content' => $ofx,
        ])->assertOk()->assertJsonPath('data.imported', 2);

        $transactionIds = collect($this->getJson('/api/reconciliation/transactions?status=pending')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertCount(2, $transactionIds);

        $this->postJson('/api/reconciliation/reconcile-many', [
            'transactions' => $transactionIds,
            'accounts' => [$firstId, $secondId],
        ])->assertUnprocessable();

        $this->getJson('/api/accounts/'.$firstId)->assertOk()->assertJsonPath('data.status', 'open');
        $this->getJson('/api/accounts/'.$secondId)->assertOk()->assertJsonPath('data.status', 'open');
        $this->getJson('/api/reconciliation/transactions?status=pending')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_recurrence_show_returns_bank_account(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $recurrenceId = $this->postJson('/api/recurrences', [
            'type' => 'payable',
            'description' => 'Internet',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 200,
            'frequency' => 'monthly',
            'start_date' => '2026-01-10',
            'day_of_month' => 10,
            'max_occurrences' => 2,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/recurrences/{$recurrenceId}")
            ->assertOk()
            ->assertJsonPath('data.bank_account_id', $bankAccountId)
            ->assertJsonPath('data.description', 'Internet');

        $this->getJson('/api/recurrences')
            ->assertOk()
            ->assertJsonPath('data.0.bank_account_id', $bankAccountId);
    }

    public function test_card_purchase_can_be_updated_without_bank_account(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');
        $costCenterId = $this->createCostCenter('Obra A');

        $cardId = $this->postJson('/api/credit-cards', [
            'name' => 'Cartão Edit',
            'institution' => 'Visa',
            'closing_day' => 10,
            'due_day' => 17,
            'bank_account_id' => $bankAccountId,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra original',
            'category_id' => $categoryId,
            'cost_center_id' => $costCenterId,
            'credit_card_id' => $cardId,
            'value' => 100,
            'purchase_date' => '2026-09-04',
        ])->assertCreated()->json('data.0.id');

        $this->putJson("/api/accounts/{$accountId}", [
            'description' => 'Compra atualizada',
            'counterparty' => 'Loja',
            'category_id' => $categoryId,
            'cost_center_id' => $costCenterId,
            'value' => 100,
            'bank_account_id' => null,
            'due_date' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Compra atualizada')
            ->assertJsonPath('data.is_card_purchase', true);
    }

    public function test_account_split_allocations_are_persisted_and_used_in_reports(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        try {
            $tenant = $this->createTenantWithRoles();
            Sanctum::actingAs($this->createAdmin($tenant));

            $bankAccountId = $this->createBankAccount();
            $categoryX = $this->createCategory('expense', 'Categoria X');
            $categoryY = $this->createCategory('expense', 'Categoria Y');
            $categoryZ = $this->createCategory('expense', 'Categoria Z');
            $obraA = $this->createCostCenter('Obra A');
            $obraB = $this->createCostCenter('Obra B');
            $obraC = $this->createCostCenter('Obra C');

            $response = $this->postJson('/api/accounts', [
                'type' => 'payable',
                'description' => 'Compra rateada',
                'bank_account_id' => $bankAccountId,
                'value' => 330,
                'due_date' => '2026-09-10',
                'purchase_date' => '2026-09-08',
                'allocations' => [
                    ['category_id' => $categoryX, 'cost_center_id' => $obraA, 'value' => 150],
                    ['category_id' => $categoryY, 'cost_center_id' => $obraB, 'value' => 100],
                    ['category_id' => $categoryZ, 'cost_center_id' => $obraC, 'value' => 80],
                ],
            ])->assertCreated();

            $this->assertCount(1, $response->json('data'));
            $this->assertSame('split', $response->json('data.0.allocation_mode'));
            $this->assertNull($response->json('data.0.category_id'));
            $this->assertNull($response->json('data.0.cost_center_id'));
            $this->assertCount(3, $response->json('data.0.allocations'));
            $this->assertEqualsWithDelta(330, collect($response->json('data.0.allocations'))->sum('value'), 0.01);

            $accountId = $response->json('data.0.id');

            $this->getJson("/api/accounts?cost_center_id={$obraB}")
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $accountId);

            $this->postJson("/api/accounts/{$accountId}/settle", [
                'value' => 330,
                'settled_at' => '2026-09-10',
            ])->assertOk();

            $daily = $this->getJson('/api/reports/daily?date=2026-09-10')->assertOk()->json('data');
            $this->assertEqualsWithDelta(330, $daily['total_paid'], 0.01);
            $this->assertCount(3, $daily['payments']);
            $this->assertEqualsCanonicalizing(
                ['Obra A', 'Obra B', 'Obra C'],
                collect($daily['groups'])->pluck('bank_account')->all(),
            );

            $byCategory = $this->getJson('/api/reports/by-category?from=2026-09-01&to=2026-09-30')->assertOk()->json('data');
            $expense = collect($byCategory['expense'])->keyBy('category');
            $this->assertEqualsWithDelta(150, $expense['Categoria X']['total'], 0.01);
            $this->assertEqualsWithDelta(100, $expense['Categoria Y']['total'], 0.01);
            $this->assertEqualsWithDelta(80, $expense['Categoria Z']['total'], 0.01);

            $byObraA = $this->getJson("/api/reports/by-category?from=2026-09-01&to=2026-09-30&cost_center_id={$obraA}")->assertOk()->json('data');
            $expenseObraA = collect($byObraA['expense'])->keyBy('category');
            $this->assertEqualsWithDelta(150, $expenseObraA['Categoria X']['total'], 0.01);
            $this->assertFalse($expenseObraA->has('Categoria Y'));
            $this->assertFalse($expenseObraA->has('Categoria Z'));

            $cashFlow = $this->getJson('/api/cash-flow/realized?from=2026-09-01&to=2026-09-30')->assertOk()->json('data');
            $this->assertEqualsWithDelta(330, $cashFlow['total_out'], 0.01);
            $this->assertCount(1, $cashFlow['entries']);
            $this->assertEqualsWithDelta(330, $cashFlow['entries'][0]['value'], 0.01);

            $dashboard = $this->getJson("/api/dashboard?cost_center_id={$obraA}")->assertOk()->json('data');
            $this->assertEqualsWithDelta(150, $dashboard['kpis']['month_expense'], 0.01);

            $this->postJson('/api/accounts', [
                'type' => 'payable',
                'description' => 'Rateio inválido',
                'bank_account_id' => $bankAccountId,
                'value' => 330,
                'due_date' => '2026-09-11',
                'allocations' => [
                    ['category_id' => $categoryX, 'cost_center_id' => $obraA, 'value' => 150],
                    ['category_id' => $categoryY, 'cost_center_id' => $obraB, 'value' => 100],
                ],
            ])->assertUnprocessable();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_split_allocations_are_distributed_across_installments(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryX = $this->createCategory('expense', 'Categoria X');
        $categoryY = $this->createCategory('expense', 'Categoria Y');
        $obraA = $this->createCostCenter('Obra A');
        $obraB = $this->createCostCenter('Obra B');

        $response = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra parcelada rateada',
            'bank_account_id' => $bankAccountId,
            'value' => 300,
            'due_date' => '2026-09-10',
            'purchase_date' => '2026-09-08',
            'installments' => ['quantity' => 3, 'interval' => 'monthly'],
            'allocations' => [
                ['category_id' => $categoryX, 'cost_center_id' => $obraA, 'value' => 180],
                ['category_id' => $categoryY, 'cost_center_id' => $obraB, 'value' => 120],
            ],
        ])->assertCreated();

        $this->assertCount(3, $response->json('data'));

        $accounts = collect($response->json('data'));
        $this->assertTrue($accounts->every(fn (array $account) => $account['allocation_mode'] === 'split'));
        $this->assertEqualsWithDelta(300, $accounts->sum('value'), 0.01);
        $this->assertEqualsWithDelta(
            180,
            $accounts->sum(fn (array $account) => collect($account['allocations'])->firstWhere('category_id', $categoryX)['value']),
            0.01,
        );
        $this->assertEqualsWithDelta(
            120,
            $accounts->sum(fn (array $account) => collect($account['allocations'])->firstWhere('category_id', $categoryY)['value']),
            0.01,
        );
    }
}
