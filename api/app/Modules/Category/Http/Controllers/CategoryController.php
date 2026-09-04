<?php

namespace App\Modules\Category\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Category\Http\Requests\StoreCategoryRequest;
use App\Modules\Category\Http\Requests\UpdateCategoryRequest;
use App\Modules\Category\Http\Resources\CategoryResource;
use App\Modules\Category\Models\Category;
use App\Modules\Category\Services\CategoryService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends ApiController
{
    public function __construct(
        private readonly CategoryService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            $request->string('type')->toString() ?: null,
            $request->string('parent')->toString() ?: null,
        );

        return $this->paginated(CategoryResource::collection($categories));
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return $this->success(CategoryResource::make($category->load(['parent', 'children'])));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->service->create($request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialCreate,
            'category',
            $category->uuid,
            ['name' => $category->name, 'type' => $category->type?->value],
        );

        return $this->created(CategoryResource::make($category->load('parent')), 'Categoria criada com sucesso.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->service->update($category, $request->validated());

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialUpdate,
            'category',
            $category->uuid,
            ['name' => $category->name],
        );

        return $this->success(CategoryResource::make($category->load('parent')), 'Categoria atualizada com sucesso.');
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->service->delete($category);

        $this->audit->recordEntity(
            $request->user(),
            AuditAction::FinancialDelete,
            'category',
            $category->uuid,
            ['name' => $category->name],
        );

        return $this->success(null, 'Categoria removida com sucesso.');
    }
}
