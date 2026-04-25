<?php

namespace App\Services;

use App\Jobs\RecalculateRiskScoreJob;
use App\Models\Contract;
use App\Models\ContractLetter;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Multitenancy\Models\Tenant;

class LetterService
{
    public function __construct(
        private WorkingDaysService $workingDays,
    ) {}

    public function paginate(Contract $contract, array $filters): LengthAwarePaginator
    {
        return $contract->letters()
            ->with(['fromCompany', 'toCompany'])
            ->when($filters['type']   ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(Contract $contract, array $data, int $userId): ContractLetter
    {
        $data['created_by']        = $userId;
        $data['response_deadline'] = $this->calculateDeadline($data);

        return $contract->letters()->create($data);
    }

    public function update(ContractLetter $letter, array $data): ContractLetter
    {
        $data['response_deadline'] = $this->calculateDeadline($data, $letter);
        $letter->update($data);
        return $letter->fresh();
    }

    public function delete(ContractLetter $letter): void
    {
        $letter->delete();
    }

    /**
     * Marca como vencidas las cartas emitidas cuya fecha límite ya pasó.
     * Retorna la colección de cartas marcadas (con contract cargado).
     */
    public function markExpired(): \Illuminate\Support\Collection
    {
        $letters = ContractLetter::query()
            ->with('contract')
            ->where('status', 'emitida')
            ->whereNotNull('response_deadline')
            ->where('response_deadline', '<', now()->toDateString())
            ->get();

        if ($letters->isEmpty()) return $letters;

        $contractIds = $letters->pluck('contract_id')->unique();

        ContractLetter::whereIn('id', $letters->pluck('id'))
            ->update(['status' => 'vencida']);

        $tenant = Tenant::current();
        if ($tenant) {
            $contractIds->each(fn ($id) =>
                RecalculateRiskScoreJob::dispatch($id, $tenant->id)
            );
        }

        return $letters;
    }

    private function calculateDeadline(array $data, ?ContractLetter $existing = null): ?string
    {
        $days    = (int) ($data['response_days'] ?? 0);
        $baseDate = $data['issued_at'] ?? ($existing?->issued_at?->toDateString() ?? null);

        if ($days <= 0 || !$baseDate) return null;

        return $this->workingDays
            ->addWorkingDays(Carbon::parse($baseDate), $days)
            ->toDateString();
    }
}
