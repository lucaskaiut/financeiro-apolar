<?php

namespace Tests\Feature\Finance;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Category\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AccountImportTest extends TestCase
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

    private function createCostCenter(string $name = 'Apolar'): string
    {
        return $this->postJson('/api/cost-centers', [
            'name' => $name,
            'status' => 'active',
        ])->assertCreated()->json('data.id');
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function makeXlsx(array $headers, array $rows, string $dataSheet = 'BASE DE DADOS'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $types = $spreadsheet->getActiveSheet();
        $types->setTitle('TIPOS DE DESPESAS');
        $types->fromArray([['TIPO', 'GRUPO'], ['Juros e IOF', 'BANCOS']]);

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($dataSheet);
        $sheet->fromArray([$headers, ...$rows]);

        $path = tempnam(sys_get_temp_dir(), 'account_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'apolar.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    public function test_import_requires_cost_center(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $file = $this->makeXlsx(
            ['EMPRESA', 'GRUPO', 'TIPO DE DESPESA', 'VENCIMENTO', 'R$ PREVISTO', '$ REALIZADO', 'PGTO', 'STATUS'],
            [['APOLAR', 'MENSAIS FIXOS', 'Agua', '08/02/2026', 147.76, 147.76, '10/03/2026', 'Pago']],
        );

        $this->post('/api/accounts/import', [
            'file' => $file,
            'bank_account_id' => $bankAccountId,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cost_center_id']);
    }

    public function test_imports_apolar_spreadsheet_with_categories_status_and_cost_center(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $costCenterId = $this->createCostCenter('Apolar');

        $file = $this->makeXlsx(
            ['EMPRESA', 'GRUPO', 'TIPO DE DESPESA', 'TIPO DE DESPESA 2', 'PREV / REAL', 'VENCIMENTO', 'R$ PREVISTO', '$ REALIZADO', 'PGTO', 'STATUS', 'OBSERVAÇÃO'],
            [
                ['APOLAR', 'MENSAIS FIXOS', 'Agua', '', 'Realizado', '08/02/2026', 147.76, 147.76, '10/03/2026', 'Pago', 'Conta de água'],
                ['APOLAR', 'MENSAIS FIXOS', 'Copel', '', 'Realizado', '01/04/2026', 150, 129, '', 'Pago', ''],
                ['APOLAR', 'BANCOS', 'Tarifa bancária', '', 'Previsto', '10/09/2026', 80, '', '', 'A Vencer', ''],
                ['APOLAR', 'BANCOS', 'Juros e IOF', '', 'Previsto', '01/01/2026', 50, '', '', 'Vencido', ''],
                ['DESPESAS PESSOAIS', 'BANCO', 'CARTÃO ALESSANDRA', 'Prudential cartão Alessandra', 'Realizado', '15/01/2026', 390, 406.6, '26/03/2026', 'Pago', ''],
                ['LG 266', '', '', '', 'Previsto', '', '', '', '', 'Vencido', ''],
            ],
        );

        $this->post('/api/accounts/import', [
            'file' => $file,
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $costCenterId,
        ], ['Accept' => 'application/json'])->assertStatus(202);

        $accounts = FinancialAccount::query()->orderBy('description')->get();

        $this->assertCount(5, $accounts);
        $this->assertTrue($accounts->every(fn (FinancialAccount $account) => $account->cost_center_id === $costCenterId));
        $this->assertTrue($accounts->every(fn (FinancialAccount $account) => $account->bank_account_id === $bankAccountId));

        $agua = $accounts->firstWhere('description', 'Agua');
        $this->assertNotNull($agua);
        $this->assertSame(AccountStatus::Settled, $agua->status);
        $this->assertSame('2026-02-08', $agua->due_date?->toDateString());
        $this->assertSame('2026-03-10', $agua->paid_date?->toDateString());
        $this->assertEqualsWithDelta(147.76, (float) $agua->value, 0.001);
        $this->assertSame('Conta de água', $agua->observation);
        $this->assertSame(1, $agua->settlements()->count());
        $this->assertSame('2026-03-10', $agua->settlements()->first()?->settled_at?->toDateString());

        $copel = $accounts->firstWhere('description', 'Copel');
        $this->assertNotNull($copel);
        $this->assertSame(AccountStatus::Settled, $copel->status);
        $this->assertSame('2026-04-01', $copel->due_date?->toDateString());
        $this->assertSame('2026-04-01', $copel->paid_date?->toDateString());
        $this->assertEqualsWithDelta(129.0, (float) $copel->value, 0.001);

        $tarifa = $accounts->firstWhere('description', 'Tarifa bancária');
        $this->assertNotNull($tarifa);
        $this->assertSame(AccountStatus::Open, $tarifa->status);
        $this->assertNull($tarifa->paid_date);
        $this->assertEqualsWithDelta(80.0, (float) $tarifa->value, 0.001);
        $this->assertSame(0, $tarifa->settlements()->count());

        $juros = $accounts->firstWhere('description', 'Juros e IOF');
        $this->assertNotNull($juros);
        $this->assertSame(AccountStatus::Open, $juros->status);

        $cartao = $accounts->firstWhere('description', 'Prudential cartão Alessandra');
        $this->assertNotNull($cartao);
        $this->assertEqualsWithDelta(406.6, (float) $cartao->value, 0.001);

        $mensais = Category::query()->whereNull('parent_id')->where('name', 'MENSAIS FIXOS')->first();
        $bancos = Category::query()->whereNull('parent_id')->where('name', 'BANCOS')->first();
        $bancoPessoal = Category::query()->whereNull('parent_id')->where('name', 'BANCO')->first();

        $this->assertNotNull($mensais);
        $this->assertNotNull($bancos);
        $this->assertNotNull($bancoPessoal);
        $this->assertSame($mensais->uuid, $agua->category_id);
        $this->assertSame($mensais->uuid, $copel->category_id);
        $this->assertSame($bancos->uuid, $tarifa->category_id);

        $aguaSub = Category::query()->where('parent_id', $mensais->uuid)->where('name', 'Agua')->first();
        $cartaoSub = Category::query()->where('parent_id', $bancoPessoal->uuid)->where('name', 'CARTÃO ALESSANDRA')->first();

        $this->assertNotNull($aguaSub);
        $this->assertSame($aguaSub->uuid, $agua->subcategory_id);
        $this->assertNotNull($cartaoSub);
        $this->assertSame($cartaoSub->uuid, $cartao->subcategory_id);
    }

    public function test_reuses_existing_category_case_insensitively(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $costCenterId = $this->createCostCenter();

        $this->postJson('/api/categories', [
            'name' => 'Mensais Fixos',
            'type' => 'expense',
            'status' => 'active',
        ])->assertCreated();

        $file = $this->makeXlsx(
            ['GRUPO', 'TIPO DE DESPESA', 'VENCIMENTO', 'R$ PREVISTO', '$ REALIZADO', 'STATUS'],
            [['MENSAIS FIXOS', 'Agua', '08/02/2026', 100, 100, 'Pago']],
        );

        $this->post('/api/accounts/import', [
            'file' => $file,
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $costCenterId,
        ], ['Accept' => 'application/json'])->assertStatus(202);

        $this->assertSame(1, Category::query()->whereNull('parent_id')->whereRaw('LOWER(name) = ?', ['mensais fixos'])->count());
        $account = FinancialAccount::query()->first();
        $this->assertSame('Mensais Fixos', $account?->category?->name);
        $this->assertSame('Agua', $account?->subcategory?->name);
    }

    public function test_imports_legacy_spreadsheet_as_settled_payables(): void
    {
        $tenant = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createAdmin($tenant));

        $bankAccountId = $this->createBankAccount();
        $costCenterId = $this->createCostCenter('Obra');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Data', 'Histórico', 'Débito (R$)', 'TIPO', 'CONSIDERAR', 'GRUPO'],
            ['10/01/2026', 'Aluguel loja', 2500, 'Aluguel', 'SIM', 'MENSAIS FIXOS'],
            ['10/01/2026', 'Ignorar', 10, 'Outros', 'NÃO', 'OUTROS'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'account_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile(
            $path,
            'legado.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $this->post('/api/accounts/import', [
            'file' => $file,
            'bank_account_id' => $bankAccountId,
            'cost_center_id' => $costCenterId,
        ], ['Accept' => 'application/json'])->assertStatus(202);

        $accounts = FinancialAccount::query()->get();
        $this->assertCount(1, $accounts);
        $account = $accounts->first();
        $this->assertSame('Aluguel loja', $account?->description);
        $this->assertSame(AccountStatus::Settled, $account?->status);
        $this->assertSame('2026-01-10', $account?->paid_date?->toDateString());
        $this->assertSame($costCenterId, $account?->cost_center_id);
        $this->assertSame('MENSAIS FIXOS', $account?->category?->name);
        $this->assertSame('Aluguel', $account?->subcategory?->name);
    }
}
