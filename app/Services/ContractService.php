<?php

namespace App\Services;

use App\Jobs\CreateSharePointFoldersJob;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\Multitenancy\Models\Tenant;

class ContractService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Contract::query()
            ->with(['mandante', 'contractor'])
            ->when($filters['search'] ?? null, fn ($q, $s) =>
                $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('number', 'like', "%{$s}%")
                ))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type']   ?? null, fn ($q, $t) => $q->where('type',   $t))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();
    }

    public function create(array $data, User $creator): Contract
    {
        $data['number']         = $this->generateNumber();
        $data['current_amount'] = $data['original_amount'];
        $data['created_by']     = $creator->id;

        // Convertir a centavos
        $data['original_amount'] = (int) round($data['original_amount'] * 100);
        $data['current_amount']  = $data['original_amount'];

        $contract = Contract::create($data);

        $tenant = Tenant::current();
        if ($tenant) {
            CreateSharePointFoldersJob::dispatch($contract->id, $tenant->id);
        }

        return $contract;
    }

    public function update(Contract $contract, array $data): Contract
    {
        // Convertir monto a centavos
        if (isset($data['original_amount'])) {
            $data['original_amount'] = (int) round($data['original_amount'] * 100);
        }

        $contract->update($data);
        return $contract->fresh();
    }

    public function changeStatus(Contract $contract, string $newStatus): Contract
    {
        if (!$contract->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "No se puede cambiar el estado de '{$contract->status}' a '{$newStatus}'.",
            ]);
        }

        $contract->update(['status' => $newStatus]);
        return $contract->fresh();
    }

    public function delete(Contract $contract): void
    {
        $contract->delete();
    }

    public function companiesForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\Company::orderBy('name')->get(['id', 'name', 'type']);
    }

    private function generateNumber(): string
    {
        $year = now()->year;
        $last = Contract::withTrashed()
            ->where('number', 'like', "{$year}-%")
            ->count();

        return sprintf('%d-%03d', $year, $last + 1);
    }
}
