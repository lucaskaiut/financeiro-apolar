<?php

namespace App\Modules\Category\Services;

use App\Modules\Category\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    /**
     * @param  'root'|'sub'|null  $parent  Filtro de hierarquia: 'root' lista apenas
     *                                      categorias principais, 'sub' apenas subcategorias
     *                                      e um uuid filtra pelos filhos daquela categoria.
     */
    public function paginate(int $perPage = 15, ?string $search = null, ?string $type = null, ?string $parent = null): LengthAwarePaginator
    {
        return Category::query()
            ->with('parent:id,uuid,name')
            ->withCount('children')
            ->when(filled($type), fn ($query) => $query->where('type', $type))
            ->when(filled($search), fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($parent === 'root', fn ($query) => $query->whereNull('parent_id'))
            ->when($parent === 'sub', fn ($query) => $query->whereNotNull('parent_id'))
            ->when(
                $parent !== null && $parent !== 'root' && $parent !== 'sub',
                fn ($query) => $query->where('parent_id', $parent),
            )
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return list<Category>
     */
    public function all(?string $type = null): array
    {
        return Category::query()
            ->with('parent:id,uuid,name')
            ->when(filled($type), fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param  array{name: string, type: string, color?: ?string, status?: string}  $data
     */
    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->fill($data);
        $category->save();

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
