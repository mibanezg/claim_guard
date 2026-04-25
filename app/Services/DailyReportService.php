<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\DailyReport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DailyReportService
{
    public function paginate(Contract $contract, array $filters): LengthAwarePaginator
    {
        return $contract->dailyReports()
            ->with('events:id,type,occurred_at')
            ->when($filters['month'] ?? null, function ($q, $month) {
                $q->whereRaw('DATE_FORMAT(report_date, "%Y-%m") = ?', [$month]);
            })
            ->orderByDesc('report_date')
            ->paginate(30)
            ->withQueryString();
    }

    public function create(Contract $contract, array $data, int $userId): DailyReport
    {
        $eventIds = $data['event_ids'] ?? [];
        unset($data['event_ids']);

        $data['contract_id']   = $contract->id;
        $data['created_by']    = $userId;
        $data['report_number'] = $this->generateNumber($contract, $data['report_date']);

        $report = DailyReport::create($data);

        if ($eventIds) {
            $report->events()->sync($eventIds);
        }

        return $report;
    }

    public function update(DailyReport $report, array $data): DailyReport
    {
        $eventIds = $data['event_ids'] ?? [];
        unset($data['event_ids']);

        $report->update($data);
        $report->events()->sync($eventIds);

        return $report->fresh();
    }

    public function delete(DailyReport $report): void
    {
        $report->events()->detach();
        $report->delete();
    }

    /**
     * Detecta días sin reporte entre dos fechas para el contrato.
     * Retorna array de fechas faltantes (máximo 60 hacia atrás).
     */
    public function missingDays(Contract $contract): array
    {
        $start = Carbon::parse($contract->actual_start_date ?? $contract->contractual_start_date);
        $end   = Carbon::now()->startOfDay();

        if ($start->gt($end)) return [];

        // Solo miramos los últimos 60 días para no sobrecargar
        if ($start->lt($end->copy()->subDays(60))) {
            $start = $end->copy()->subDays(60);
        }

        $existing = $contract->dailyReports()
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('report_date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        $missing = [];
        $cursor  = $start->copy();
        while ($cursor->lte($end)) {
            if (!in_array($cursor->toDateString(), $existing)) {
                $missing[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $missing;
    }

    private function generateNumber(Contract $contract, string $date): string
    {
        $count = $contract->dailyReports()->withTrashed()->count() + 1;
        return 'RO-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
