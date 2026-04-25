<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Company::query()
            ->when($filters['search'] ?? null, fn ($q, $search) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('rut', 'like', "%{$search}%")
            )
            ->when($filters['type'] ?? null, fn ($q, $type) =>
                $q->where('type', $type)
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(Company $company, array $data): Company
    {
        $company->update($data);
        return $company->fresh();
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }

    public function allForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Company::orderBy('name')->get(['id', 'name', 'rut', 'type']);
    }
}
