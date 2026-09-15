<?php

namespace Tests\Feature\Finance;

use App\Modules\Account\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InstallmentTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private function createBankAccount(): string
    {
        return $this->postJson('/api/bank-accounts', [
            'name' => 'Banco Principal',
            'bank' => 'Banco do Brasil',
            'agency' => '0001',
            'account' => '12345-6',
            'type' => 'checking',
            'initial_balance' => 1000,
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }

    private function createCategory(string $type = 'expense'): string
    {
        return $this->postJson('/api/categories', [
            'name' => 'Fornecedores',
            'type' => $type,
            'color' => '#6366f1',
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }

    private function createInstallmentAccount(string $bankAccountId, string $categoryId, string $description, int $quantity): string
    {
        $response = $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => $description,
            'counterparty' => 'Fornecedor X',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 1200,
            'due_date' => '2026-01-10',
            'purchase_date' => '2026-01-10',
            'installments' => ['quantity' => $quantity, 'interval' => 'monthly'],
        ])->assertCreated();

        return $response->json('data.0.installment_group_id');
    }

    public function test_it_lists_installment_groups_with_totals(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $groupId = $this->createInstallmentAccount($bankAccountId, $categoryId, 'Compra de equipamentos', 3);

        // Um lançamento avulso (sem parcelas) não deve aparecer como parcelamento.
        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Conta avulsa',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 100,
            'due_date' => '2026-02-10',
        ])->assertCreated();

        $this->getJson('/api/installments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $groupId)
            ->assertJsonPath('data.0.description', 'Compra de equipamentos')
            ->assertJsonPath('data.0.installments_count', 3)
            ->assertJsonPath('data.0.installment_total', 3)
            ->assertJsonPath('data.0.total_value', 1200.0);
    }

    public function test_it_shows_all_installments_of_a_group(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $groupId = $this->createInstallmentAccount($bankAccountId, $categoryId, 'Compra de equipamentos', 4);

        $this->getJson("/api/installments/{$groupId}")
            ->assertOk()
            ->assertJsonPath('data.installments_count', 4)
            ->assertJsonPath('data.installment_total', 4)
            ->assertJsonCount(4, 'data.installments')
            ->assertJsonPath('data.installments.0.installment_number', 1)
            ->assertJsonPath('data.installments.3.installment_number', 4);
    }

    public function test_it_returns_404_for_unknown_group(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $this->getJson('/api/installments/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_it_searches_installment_groups(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $this->createInstallmentAccount($bankAccountId, $categoryId, 'Compra de equipamentos', 2);
        $this->createInstallmentAccount($bankAccountId, $categoryId, 'Serviço de consultoria', 2);

        $this->getJson('/api/installments?search=consultoria')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Serviço de consultoria');
    }

    public function test_it_scopes_groups_to_current_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenantA));
        $bankAccountA = $this->createBankAccount();
        $categoryA = $this->createCategory('expense');
        $this->createInstallmentAccount($bankAccountA, $categoryA, 'Parcelamento do tenant A', 3);

        Sanctum::actingAs($this->createAdmin($tenantB));
        $bankAccountB = $this->createBankAccount();
        $categoryB = $this->createCategory('expense');
        $this->createInstallmentAccount($bankAccountB, $categoryB, 'Parcelamento do tenant B', 3);

        $this->getJson('/api/installments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Parcelamento do tenant B');
    }

    public function test_it_creates_account_with_custom_installments(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Serviço parcelado personalizado',
            'counterparty' => 'Fornecedor Y',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-01-10',
            'installments' => [
                'quantity' => 3,
                'interval' => 'monthly',
                'items' => [
                    ['value' => 300, 'due_date' => '2026-01-10'],
                    ['value' => 400, 'due_date' => '2026-03-05'],
                    ['value' => 300, 'due_date' => '2026-05-20'],
                ],
            ],
        ])->assertCreated();

        $accounts = FinancialAccount::query()->orderBy('installment_number')->get();

        $this->assertCount(3, $accounts);
        $this->assertEquals([300.0, 400.0, 300.0], $accounts->pluck('value')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(['2026-01-10', '2026-03-05', '2026-05-20'], $accounts->pluck('due_date')->map(fn ($d) => $d?->toDateString())->all());
        $this->assertEqualsCanonicalizing(range(1, 3), $accounts->pluck('installment_number')->all());
    }

    public function test_it_rejects_custom_installments_when_sum_differs_from_value(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $categoryId = $this->createCategory('expense');

        $this->postJson('/api/accounts', [
            'type' => 'payable',
            'description' => 'Serviço parcelado inválido',
            'counterparty' => 'Fornecedor Y',
            'bank_account_id' => $bankAccountId,
            'category_id' => $categoryId,
            'value' => 1000,
            'due_date' => '2026-01-10',
            'installments' => [
                'quantity' => 2,
                'interval' => 'monthly',
                'items' => [
                    ['value' => 300, 'due_date' => '2026-01-10'],
                    ['value' => 300, 'due_date' => '2026-02-10'],
                ],
            ],
        ])->assertStatus(422);
    }
}
