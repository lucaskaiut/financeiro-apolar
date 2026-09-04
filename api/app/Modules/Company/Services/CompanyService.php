<?php

namespace App\Modules\Company\Services;

use App\Modules\Company\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Company::query()
            ->when(filled($search), fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return list<Company>
     */
    public function all(): array
    {
        return Company::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param  array{name: string, status?: string}  $data
     */
    public function create(array $data): Company
    {
        return Company::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        $company->fill($data);
        $company->save();

        return $company->refresh();
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }
}
