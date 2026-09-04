<?php

namespace App\Modules\Account\Http\Controllers;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Exceptions\AccountUpdateException;
use App\Modules\Account\Http\Requests\ImportAccountsRequest;
use App\Modules\Account\Http\Requests\SettleAccountRequest;
use App\Modules\Account\Http\Requests\StoreAccountRequest;
use App\Modules\Account\Http\Requests\UpdateAccountRequest;
use App\Modules\Account\Http\Resources\AccountDocumentResource;
use App\Modules\Account\Http\Resources\AccountResource;
use App\Modules\Account\Jobs\ImportAccountsJob;
use App\Modules\Account\Models\AccountDocument;
use App\Modules\Account\Models\AccountImport;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Account\Services\AccountService;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends ApiController
{
    public function __construct(
        private readonly AccountService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $accounts = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->only([
                'search',
                'type',
                'status',
                'overdue',
                'cost_center_id',
                'category_id',
                'due_from',
                'due_to',
                'paid_from',
                'paid_to',
                'installment_group_id',
            ]),
        );

        return $this->paginated(AccountResource::collection($accounts));
    }

    public function show(FinancialAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        $account = $this->service->find($account->uuid);

        return $this->success(AccountResource::make($account));
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $accounts = $this->service->create($request->validated());

        foreach ($accounts as $account) {
            $this->audit->recordEntity(
                $request->user(),
                $account->installment_total > 1 ? AuditAction::InstallmentGenerate : AuditAction::FinancialCreate,
                'account',
                $account->uuid,
                [
                    'description' => $account->description,
                    'type' => $account->type?->value,
                    'installment' => $account->installment_total > 1 ? "{$account->installment_number}/{$account->installment_total}" : null,
                ],
            );
        }

        return $this->created(
            AccountResource::collection($accounts),
            count($accounts) > 1 ? 'Parcelamento gerado com sucesso.' : 'Conta criada com sucesso.',
        );
    }

    public function import(ImportAccountsRequest $request): JsonResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $file = $request->file('file');

        $record = AccountImport::query()->create([
            'tenant_id' => TenantContext::tenantId(),
            'user_id' => $request->user()?->getKey(),
            'cost_center_id' => $request->string('cost_center_id')->toString(),
            'filename' => $file->getClientOriginalName(),
            'content' => base64_encode($file->get()),
        ]);

        ImportAccountsJob::dispatch($record->getKey());

        return $this->success(null, 'Importação iniciada. A planilha será processada em segundo plano.', 202);
    }

    public function update(UpdateAccountRequest $request, FinancialAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        if ($account->isReconciled()) {
            throw ValidationException::withMessages([
                'account' => ['Lançamentos conciliados não podem ser alterados.'],
            ]);
        }

        try {
            $account = $this->service->update($account, $request->validated());
        } catch (AccountUpdateException $e) {
            throw ValidationException::withMessages([$e->field => [$e->getMessage()]]);
        }

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'account',
            $account->uuid,
            ['description' => $account->description],
        );

        return $this->success(AccountResource::make($this->service->find($account->uuid)), 'Conta atualizada com sucesso.');
    }

    public function destroy(Request $request, FinancialAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);

        if ($account->isReconciled()) {
            throw ValidationException::withMessages([
                'account' => ['Lançamentos conciliados não podem ser excluídos.'],
            ]);
        }

        if ($account->settled_amount > 0) {
            throw ValidationException::withMessages([
                'account' => ['Lançamentos com baixas não podem ser excluídos.'],
            ]);
        }

        $this->service->delete($account);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialDelete,
            'account',
            $account->uuid,
            ['description' => $account->description],
        );

        return $this->success(null, 'Conta removida com sucesso.');
    }

    public function settle(SettleAccountRequest $request, FinancialAccount $account): JsonResponse
    {
        $this->authorize('settle', $account);

        if ($account->status === AccountStatus::Cancelled) {
            throw ValidationException::withMessages(['account' => ['Contas canceladas não podem ser baixadas.']]);
        }

        $settlement = $this->service->settle($account, $request->user(), $request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::AccountSettle,
            'account',
            $account->uuid,
            ['description' => $account->description, 'value' => (float) $settlement->value],
        );

        $account = $this->service->find($account->uuid);

        return $this->success(AccountResource::make($account), 'Baixa registrada com sucesso.');
    }

    public function unsettle(Request $request, FinancialAccount $account, Settlement $settlement): JsonResponse
    {
        $this->authorize('settle', $account);

        if ($settlement->account_id !== $account->getKey()) {
            throw ValidationException::withMessages(['settlement' => ['Baixa não pertence a esta conta.']]);
        }

        $this->service->unsettle($account, $settlement);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::AccountUnsettle,
            'account',
            $account->uuid,
            ['description' => $account->description, 'value' => (float) $settlement->value],
        );

        $account = $this->service->find($account->uuid);

        return $this->success(AccountResource::make($account), 'Baixa removida com sucesso.');
    }

    public function reopen(Request $request, FinancialAccount $account): JsonResponse
    {
        $this->authorize('settle', $account);

        try {
            $result = $this->service->reopen($account);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['account' => [$e->getMessage()]]);
        }

        $account = $result['account'];

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::AccountReopen,
            'account',
            $account->uuid,
            [
                'description' => $account->description,
                'settlements_removed' => $result['settlements_removed'],
                'reversed_amount' => $result['reversed_amount'],
                'reconciliations_reversed' => $result['reconciliations_reversed'],
            ],
        );

        if ($result['reconciliations_reversed'] > 0) {
            $this->audit->recordEntity(
                $request->user(),
                AuditAction::ReconciliationUndo,
                'account',
                $account->uuid,
                [
                    'description' => $account->description,
                    'source' => 'account_reopen',
                    'reconciliations_reversed' => $result['reconciliations_reversed'],
                ],
            );
        }

        $account = $this->service->find($account->uuid);

        return $this->success(AccountResource::make($account), 'Conta reaberta com sucesso.');
    }

    public function cancel(Request $request, FinancialAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        if ($account->isReconciled() || $account->settled_amount > 0) {
            throw ValidationException::withMessages(['account' => ['Contas conciliadas ou com baixas não podem ser canceladas.']]);
        }

        $account = $this->service->cancel($account);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'account',
            $account->uuid,
            ['description' => $account->description, 'status' => 'cancelled'],
        );

        return $this->success(AccountResource::make($account), 'Conta cancelada com sucesso.');
    }

    public function indexDocuments(FinancialAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        $documents = $account->documents()->orderBy('created_at')->get();

        return $this->success(AccountDocumentResource::collection($documents));
    }

    public function storeDocuments(Request $request, FinancialAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,gif,webp,bmp,svg,doc,docx,xls,xlsx,csv,txt',
            ],
        ]);

        $documents = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('documents', 'local');

            if ($path === false) {
                throw new \RuntimeException('Não foi possível armazenar o documento.');
            }

            $documents[] = $account->documents()->create([
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'user_id' => $request->user()?->getKey(),
            ]);
        }

        return $this->created(AccountDocumentResource::collection($documents), 'Documentos anexados com sucesso.');
    }

    public function downloadDocument(Request $request, FinancialAccount $account, AccountDocument $document): StreamedResponse
    {
        $this->authorize('view', $account);

        $this->ensureDocumentBelongsTo($account, $document);

        $headers = ['Content-Type' => $document->mime_type ?: 'application/octet-stream'];

        if (! Storage::disk('local')->exists($document->path)) {
            abort(404, 'Documento não encontrado.');
        }

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($document->path, $document->name, $headers);
        }

        return Storage::disk('local')->response($document->path, $document->name, $headers);
    }

    public function destroyDocument(FinancialAccount $account, AccountDocument $document): JsonResponse
    {
        $this->authorize('update', $account);

        $this->ensureDocumentBelongsTo($account, $document);

        $document->delete();

        return $this->success(null, 'Documento removido com sucesso.');
    }

    private function ensureDocumentBelongsTo(FinancialAccount $account, AccountDocument $document): void
    {
        if ($document->account_id !== $account->getKey()) {
            throw ValidationException::withMessages([
                'document' => ['Documento não pertence a esta conta.'],
            ]);
        }
    }
}
