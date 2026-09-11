<?php

namespace App\Modules\Reconciliation\Http\Controllers;

use App\Modules\Account\Http\Resources\AccountResource;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Reconciliation\Http\Requests\CreateFromTransactionRequest;
use App\Modules\Reconciliation\Http\Requests\ImportOfxRequest;
use App\Modules\Reconciliation\Http\Requests\ReconcileManyRequest;
use App\Modules\Reconciliation\Http\Requests\ReconcileRequest;
use App\Modules\Reconciliation\Http\Resources\BankTransactionResource;
use App\Modules\Reconciliation\Models\BankTransaction;
use App\Modules\Reconciliation\Services\ReconciliationService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReconciliationController extends ApiController
{
    public function __construct(
        private readonly ReconciliationService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('status')->toString() ?: null,
            $request->string('bank_account_id')->toString() ?: null,
        );

        return $this->paginated(BankTransactionResource::collection($transactions));
    }

    public function candidates(BankTransaction $transaction, Request $request): JsonResponse
    {
        $exact = $request->has('exact') ? $request->boolean('exact') : true;

        $candidates = $this->service->candidates(
            $transaction,
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            exactValue: $exact,
        );

        return $this->success([
            'transaction' => BankTransactionResource::make($transaction->load('bankAccount:id,uuid,name')),
            'candidates' => AccountResource::collection($candidates),
        ]);
    }

    public function identify(Request $request): JsonResponse
    {
        $matches = $this->service->identify(
            $request->string('bank_account_id')->toString() ?: null,
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $data = [];

        foreach ($matches as $transactionId => $candidates) {
            $data[$transactionId] = AccountResource::collection($candidates);
        }

        return $this->success($data);
    }

    public function import(ImportOfxRequest $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $result = $this->service->importSpreadsheet(
                $request->string('bank_account_id')->toString(),
                (string) $request->file('file')?->getRealPath(),
            );
        } else {
            $result = $this->service->import(
                $request->string('bank_account_id')->toString(),
                $request->string('content')->toString(),
            );
        }

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::ReconciliationExecute,
            'bank_transaction',
            $request->string('bank_account_id')->toString(),
            ['imported' => $result['imported']],
        );

        return $this->success($result, 'Importação do extrato concluída.');
    }

    public function autoReconcile(Request $request): JsonResponse
    {
        $result = $this->service->autoReconcile(
            $request->user(),
            $request->string('bank_account_id')->toString() ?: null,
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::ReconciliationExecute,
            'reconciliation',
            'auto',
            $result,
        );

        return $this->success($result, 'Conciliação automática concluída.');
    }

    public function reconcile(ReconcileRequest $request, BankTransaction $transaction): JsonResponse
    {
        $account = FinancialAccount::query()->where('uuid', $request->string('account_id'))->firstOrFail();

        try {
            $this->service->reconcile($transaction, $account, $request->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['account_id' => [$e->getMessage()]]);
        }

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::ReconciliationExecute,
            'bank_transaction',
            $transaction->uuid,
            ['account' => $account->uuid],
        );

        return $this->success(BankTransactionResource::make($transaction->refresh()), 'Transação conciliada com sucesso.');
    }

    public function reconcileMany(ReconcileManyRequest $request): JsonResponse
    {
        try {
            $this->service->reconcileMany(
                $request->input('transactions'),
                $request->input('accounts'),
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['accounts' => [$e->getMessage()]]);
        }

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::ReconciliationExecute,
            'reconciliation',
            'many',
            [
                'transactions' => $request->input('transactions'),
                'accounts' => $request->input('accounts'),
            ],
        );

        return $this->success(null, 'Conciliação múltipla realizada com sucesso.');
    }

    public function ignore(Request $request, BankTransaction $transaction): JsonResponse
    {
        $this->service->ignore($transaction);

        return $this->success(BankTransactionResource::make($transaction->refresh()), 'Transação ignorada.');
    }

    public function createAccount(CreateFromTransactionRequest $request, BankTransaction $transaction): JsonResponse
    {
        try {
            $account = $this->service->createFromTransaction($transaction, $request->validated(), $request->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['value' => [$e->getMessage()]]);
        }

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::ReconciliationExecute,
            'bank_transaction',
            $transaction->uuid,
            [
                'account' => $account->uuid,
                'account_ids' => $request->input('account_ids', []),
            ],
        );

        return $this->created(AccountResource::make($account->load(['costCenter:id,uuid,name', 'category:id,uuid,name', 'bankAccount:id,uuid,name'])), 'Lançamento criado a partir do extrato.');
    }

    public function undo(Request $request, BankTransaction $transaction): JsonResponse
    {
        try {
            $this->service->undo($transaction);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['transaction' => [$e->getMessage()]]);
        }

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::ReconciliationUndo,
            'bank_transaction',
            $transaction->uuid,
        );

        return $this->success(BankTransactionResource::make($transaction->refresh()), 'Conciliação desfeita com sucesso.');
    }
}
