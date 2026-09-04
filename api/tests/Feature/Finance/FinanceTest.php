<?php

namespace Tests\Feature\Finance;

use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Report\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private function createCostCenter(): string
    {
        $response = $this->postJson('/api/cost-centers', [
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

    private function createCategory(string $type = 'expense'): string
    {
        $response = $this->postJson('/api/categories', [
            'name' => 'Fornecedores',
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

        $costCenterId = $this->createCostCenter();

        $this->getJson('/api/cost-centers')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/cost-centers/{$costCenterId}")->assertOk()->assertJsonPath('data.name', 'Banco Principal');

        $this->putJson("/api/cost-centers/{$costCenterId}", ['name' => 'Banco Renomeado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Banco Renomeado');

        $categoryId = $this->createCategory('income');

        $this->getJson('/api/categories?type=income')->assertOk()->assertJsonPath('meta.total', 1);
        $this->putJson("/api/categories/{$categoryId}", ['name' => 'Clientes'])->assertOk()->assertJsonPath('data.name', 'Clientes');

        $this->deleteJson("/api/categories/{$categoryId}")->assertOk();
        $this->deleteJson("/api/cost-centers/{$costCenterId}")->assertOk();
    }

    public function test_account_create_and_installment_generation(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $response = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra de equipamentos',
            'counterparty' => 'Fornecedor X',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1200,
            'due_date' => '2026-01-10',
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

    public function test_account_settle_partial_and_full(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Aluguel',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-02-01',
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Aluguel',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-02-01',
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountA = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta A',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => '2026-03-01',
        ])->json('data.0.id');

        $accountB = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta B',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 200,
            'due_date' => '2026-03-01',
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

    public function test_accounts_overdue_filter_returns_unpaid_past_due(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $overdueId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta vencida',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 150,
            'due_date' => now()->subDays(5)->toDateString(),
        ])->json('data.0.id');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta futura',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 250,
            'due_date' => now()->addDays(5)->toDateString(),
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('income');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'receivable',
            'description' => 'Venda',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => '2026-05-01',
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Fornecedor',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-06-01',
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Aluguel',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 500,
            'due_date' => '2026-07-01',
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Fatura',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 500,
            'due_date' => '2026-03-01',
        ])->json('data.0.id');

        $this->postJson("/api/accounts/{$accountId}/settle", ['value' => 500])->assertOk();

        $this->deleteJson("/api/accounts/{$accountId}")->assertUnprocessable();
    }

    public function test_settled_account_can_be_reopened(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Energia',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 500,
            'due_date' => '2026-03-01',
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/recurrences', [
            'type' => 'payable',
            'description' => 'Internet',
            'cost_center_id' => $costCenterId,
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

        $fromId = $this->createCostCenter();
        $toId = $this->postJson('/api/cost-centers', [
            'name' => 'Banco B',
            'type' => 'checking',
            'initial_balance' => 0,
            'status' => 'active',
        ])->json('data.id');

        $this->postJson('/api/transfers', [
            'from_cost_center_id' => $fromId,
            'to_cost_center_id' => $toId,
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'receivable',
            'description' => 'Venda',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 800,
            'due_date' => '2026-04-01',
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

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Internet',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 200,
            'due_date' => '2026-08-10',
        ])->assertCreated();

        $ofx = <<<OFX
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
            'cost_center_id' => $costCenterId,
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

    public function test_payables_report_treats_unselected_due_today_as_overdue(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        try {
            $tenant = $this->createTenantWithRoles();
            Sanctum::actingAs($this->createAdmin($tenant));

            $costCenterId = $this->createCostCenter();
            $categoryId = $this->createCategory('expense');

            $dueTodayId = $this->postJson('/api/accounts', [
                'type' => 'payable',
                'description' => 'Conta do dia',
                'cost_center_id' => $costCenterId,
                'category_id' => $categoryId,
                'value' => 500,
                'due_date' => '2026-09-01',
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
        $this->getJson('/api/reports/cash-flow')->assertOk();
        $this->getJson('/api/reports/payables')->assertOk();
    }

    public function test_account_documents_upload_list_preview_and_delete(): void
    {
        Storage::fake('local');

        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $costCenterId = $this->createCostCenter();
        $categoryId = $this->createCategory('expense');

        $accountId = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Compra com anexos',
            'cost_center_id' => $costCenterId,
            'category_id' => $categoryId,
            'value' => 300,
            'due_date' => '2026-09-01',
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
}
