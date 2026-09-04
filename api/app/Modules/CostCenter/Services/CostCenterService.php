<?php

namespace App\Modules\CostCenter\Services;

use App\Modules\CostCenter\Models\CostCenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CostCenterService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return CostCenter::query()
            ->when(filled($search), fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return list<CostCenter>
     */
    public function all(): array
    {
        return CostCenter::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param  array{name: string, status?: string}  $data
     */
    public function create(array $data): CostCenter
    {
        return CostCenter::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CostCenter $costCenter, array $data): CostCenter
    {
        $costCenter->fill($data);
        $costCenter->save();

        return $costCenter->refresh();
    }

    public function delete(CostCenter $costCenter): void
    {
        $costCenter->delete();
    }
}
