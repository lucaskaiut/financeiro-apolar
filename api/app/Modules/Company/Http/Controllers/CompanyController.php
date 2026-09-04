<?php

namespace App\Modules\Company\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Company\Http\Requests\StoreCompanyRequest;
use App\Modules\Company\Http\Requests\UpdateCompanyRequest;
use App\Modules\Company\Http\Resources\CompanyResource;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Services\CompanyService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function __construct(
        private readonly CompanyService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(CompanyResource::collection($companies));
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        return $this->success(CompanyResource::make($company));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->service->create($request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialCreate,
            'company',
            $company->uuid,
            ['name' => $company->name],
        );

        return $this->created(CompanyResource::make($company), 'Empresa criada com sucesso.');
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $company = $this->service->update($company, $request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'company',
            $company->uuid,
            ['name' => $company->name],
        );

        return $this->success(CompanyResource::make($company), 'Empresa atualizada com sucesso.');
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $this->service->delete($company);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialDelete,
            'company',
            $company->uuid,
            ['name' => $company->name],
        );

        return $this->success(null, 'Empresa removida com sucesso.');
    }
}
