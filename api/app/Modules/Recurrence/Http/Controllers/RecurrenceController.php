<?php

namespace App\Modules\Recurrence\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Recurrence\Http\Requests\StoreRecurrenceRequest;
use App\Modules\Recurrence\Http\Requests\UpdateRecurrenceRequest;
use App\Modules\Recurrence\Http\Resources\RecurrenceResource;
use App\Modules\Recurrence\Models\Recurrence;
use App\Modules\Recurrence\Services\RecurrenceService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurrenceController extends ApiController
{
    public function __construct(
        private readonly RecurrenceService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Recurrence::class);

        $recurrences = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(RecurrenceResource::collection($recurrences));
    }

    public function show(Recurrence $recurrence): JsonResponse
    {
        $this->authorize('view', $recurrence);

        return $this->success(RecurrenceResource::make($recurrence->load(['costCenter:id,uuid,name', 'category:id,uuid,name', 'subcategory:id,uuid,name'])));
    }

    public function store(StoreRecurrenceRequest $request): JsonResponse
    {
        $this->authorize('create', Recurrence::class);

        $recurrence = $this->service->create($request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::RecurrenceGenerate,
            'recurrence',
            $recurrence->uuid,
            ['description' => $recurrence->description, 'frequency' => $recurrence->frequency?->value],
        );

        return $this->created(RecurrenceResource::make($recurrence), 'Recorrência criada com sucesso.');
    }

    public function update(UpdateRecurrenceRequest $request, Recurrence $recurrence): JsonResponse
    {
        $this->authorize('update', $recurrence);

        $scope = (string) $request->string('scope', 'all');
        $data = $request->validated();
        unset($data['scope']);

        $recurrence = $this->service->update($recurrence, $data, $scope);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'recurrence',
            $recurrence->uuid,
            ['description' => $recurrence->description, 'scope' => $scope],
        );

        return $this->success(RecurrenceResource::make($recurrence), 'Recorrência atualizada com sucesso.');
    }

    public function destroy(Request $request, Recurrence $recurrence): JsonResponse
    {
        $this->authorize('delete', $recurrence);

        $this->service->delete($recurrence);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialDelete,
            'recurrence',
            $recurrence->uuid,
            ['description' => $recurrence->description],
        );

        return $this->success(null, 'Recorrência removida com sucesso.');
    }
}
