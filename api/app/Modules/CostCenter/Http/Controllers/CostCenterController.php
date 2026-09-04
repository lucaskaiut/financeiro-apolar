<?php

namespace App\Modules\CostCenter\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\CostCenter\Http\Requests\StoreCostCenterRequest;
use App\Modules\CostCenter\Http\Requests\UpdateCostCenterRequest;
use App\Modules\CostCenter\Http\Resources\CostCenterResource;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\CostCenter\Services\CostCenterService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CostCenterController extends ApiController
{
    public function __construct(
        private readonly CostCenterService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CostCenter::class);

        $costCenters = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(CostCenterResource::collection($costCenters));
    }

    public function show(CostCenter $costCenter): JsonResponse
    {
        $this->authorize('view', $costCenter);

        return $this->success(CostCenterResource::make($costCenter));
    }

    public function store(StoreCostCenterRequest $request): JsonResponse
    {
        $this->authorize('create', CostCenter::class);

        $costCenter = $this->service->create($request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialCreate,
            'cost_center',
            $costCenter->uuid,
            ['name' => $costCenter->name],
        );

        return $this->created(CostCenterResource::make($costCenter), 'Centro de custo criado com sucesso.');
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter): JsonResponse
    {
        $this->authorize('update', $costCenter);

        $costCenter = $this->service->update($costCenter, $request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'cost_center',
            $costCenter->uuid,
            ['name' => $costCenter->name],
        );

        return $this->success(CostCenterResource::make($costCenter), 'Centro de custo atualizado com sucesso.');
    }

    public function destroy(Request $request, CostCenter $costCenter): JsonResponse
    {
        $this->authorize('delete', $costCenter);

        $this->service->delete($costCenter);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialDelete,
            'cost_center',
            $costCenter->uuid,
            ['name' => $costCenter->name],
        );

        return $this->success(null, 'Centro de custo removido com sucesso.');
    }
}
