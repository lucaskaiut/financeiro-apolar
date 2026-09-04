<?php

namespace App\Modules\Transfer\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Transfer\Http\Requests\StoreTransferRequest;
use App\Modules\Transfer\Http\Resources\TransferResource;
use App\Modules\Transfer\Models\Transfer;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends ApiController
{
    public function __construct(
        private readonly TransferService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transfer::class);

        $transfers = $this->service->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(TransferResource::collection($transfers));
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $this->authorize('create', Transfer::class);

        $transfer = $this->service->create($request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialCreate,
            'transfer',
            $transfer->uuid,
            ['value' => (float) $transfer->value],
        );

        return $this->created(TransferResource::make($transfer), 'Transferência realizada com sucesso.');
    }

    public function destroy(Request $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('delete', $transfer);

        $this->service->delete($transfer);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialDelete,
            'transfer',
            $transfer->uuid,
            ['value' => (float) $transfer->value],
        );

        return $this->success(null, 'Transferência removida com sucesso.');
    }
}
