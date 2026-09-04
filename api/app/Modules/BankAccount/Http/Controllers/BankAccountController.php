<?php

namespace App\Modules\BankAccount\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\BankAccount\Http\Requests\StoreBankAccountRequest;
use App\Modules\BankAccount\Http\Requests\UpdateBankAccountRequest;
use App\Modules\BankAccount\Http\Resources\BankAccountResource;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\BankAccount\Services\BankAccountService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends ApiController
{
    public function __construct(
        private readonly BankAccountService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(BankAccountResource::collection($bankAccounts));
    }

    public function show(BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('view', $bankAccount);

        return $this->success(BankAccountResource::make($bankAccount));
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $this->authorize('create', BankAccount::class);

        $bankAccount = $this->service->create($request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialCreate,
            'bank_account',
            $bankAccount->uuid,
            ['name' => $bankAccount->name],
        );

        return $this->created(BankAccountResource::make($bankAccount), 'Conta bancária criada com sucesso.');
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('update', $bankAccount);

        $bankAccount = $this->service->update($bankAccount, $request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'bank_account',
            $bankAccount->uuid,
            ['name' => $bankAccount->name],
        );

        return $this->success(BankAccountResource::make($bankAccount), 'Conta bancária atualizada com sucesso.');
    }

    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('delete', $bankAccount);

        $this->service->delete($bankAccount);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialDelete,
            'bank_account',
            $bankAccount->uuid,
            ['name' => $bankAccount->name],
        );

        return $this->success(null, 'Conta bancária removida com sucesso.');
    }
}
