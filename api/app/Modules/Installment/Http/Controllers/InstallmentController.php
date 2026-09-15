<?php

namespace App\Modules\Installment\Http\Controllers;

use App\Modules\Account\Exceptions\AccountUpdateException;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Installment\Http\Requests\UpdateInstallmentRequest;
use App\Modules\Installment\Http\Resources\InstallmentDetailResource;
use App\Modules\Installment\Http\Resources\InstallmentResource;
use App\Modules\Installment\Services\InstallmentService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InstallmentController extends ApiController
{
    public function __construct(
        private readonly InstallmentService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $installments = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            (int) $request->integer('page', 1),
        );

        return $this->paginated(InstallmentResource::collection($installments));
    }

    public function show(string $group): JsonResponse
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $data = $this->service->find($group);

        if ($data === null) {
            abort(404, 'Parcelamento não encontrado.');
        }

        return $this->success(InstallmentDetailResource::make($data));
    }

    public function update(UpdateInstallmentRequest $request, string $group): JsonResponse
    {
        $scope = (string) $request->string('scope', 'all');
        $data = $request->validated();
        unset($data['scope']);

        try {
            $data = $this->service->update($group, $data, $scope);
        } catch (AccountUpdateException $e) {
            throw ValidationException::withMessages([$e->field => [$e->getMessage()]]);
        }

        if ($data === null) {
            abort(404, 'Parcelamento não encontrado.');
        }

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'installment',
            $group,
            ['description' => $data->description, 'scope' => $scope],
        );

        return $this->success(InstallmentDetailResource::make($data), 'Parcelamento atualizado com sucesso.');
    }
}
