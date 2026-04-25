<?php

namespace App\Services;

use App\Jobs\RecalculateRiskScoreJob;
use App\Models\Contract;
use App\Models\ContractualEvent;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Multitenancy\Models\Tenant;

class EventService
{
    public function __construct(private WorkingDaysService $workingDays) {}

    public function paginate(Contract $contract, array $filters): LengthAwarePaginator
    {
        return $contract->events()
            ->when($filters['type']              ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['responsible_party'] ?? null, fn ($q, $v) => $q->where('responsible_party', $v))
            ->when($filters['resolution_status'] ?? null, fn ($q, $v) => $q->where('resolution_status', $v))
            ->when($filters['notification_status'] ?? null, fn ($q, $v) => $q->where('notification_status', $v))
            ->orderByDesc('occurred_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(Contract $contract, array $data, int $userId): ContractualEvent
    {
        $data['cost_impact']    = (int) round(($data['cost_impact'] ?? 0) * 100);
        $data['created_by']     = $userId;
        $data['notice_deadline'] = $this->calculateNoticeDeadline($contract, $data);
        $data['notification_status'] = $this->deriveNotificationStatus($data);

        $event = $contract->events()->create($data);
        $this->dispatchRiskRecalculation($contract);
        return $event;
    }

    public function update(ContractualEvent $event, array $data): ContractualEvent
    {
        $data['cost_impact']    = (int) round(($data['cost_impact'] ?? 0) * 100);
        $data['notice_deadline'] = $this->calculateNoticeDeadline($event->contract, $data);
        $data['notification_status'] = $this->deriveNotificationStatus($data);

        $event->update($data);
        $this->dispatchRiskRecalculation($event->contract);
        return $event->fresh();
    }

    public function delete(ContractualEvent $event): void
    {
        $contract = $event->contract;
        $event->delete();
        $this->dispatchRiskRecalculation($contract);
    }

    /**
     * Calcula la fecha límite de notificación en días hábiles chilenos.
     * Usa notification_days del contrato, salvo que el tipo sea no aplicable.
     */
    private function calculateNoticeDeadline(Contract $contract, array $data): ?string
    {
        // Si el usuario marcó no_aplica, no calculamos
        if (($data['notification_status'] ?? '') === 'no_aplica') return null;

        $days = (int) ($contract->notification_days ?? 0);
        if ($days <= 0 || empty($data['occurred_at'])) return null;

        return $this->workingDays
            ->addWorkingDays(Carbon::parse($data['occurred_at']), $days)
            ->toDateString();
    }

    /**
     * Determina el estado de notificación basado en si hay fecha de notificación
     * y si esta fue antes o después del plazo.
     */
    private function deriveNotificationStatus(array $data): string
    {
        // Si el usuario lo marcó explícitamente como no_aplica, respetamos
        if (($data['notification_status'] ?? '') === 'no_aplica') return 'no_aplica';

        $notifiedAt      = $data['notified_at'] ?? null;
        $noticeDeadline  = $data['notice_deadline'] ?? null;

        if (!$notifiedAt) return 'pendiente';

        if ($noticeDeadline && Carbon::parse($notifiedAt)->gt(Carbon::parse($noticeDeadline))) {
            return 'notificado_tarde';
        }

        return 'notificado_a_tiempo';
    }

    private function dispatchRiskRecalculation(Contract $contract): void
    {
        $tenant = Tenant::current();
        if ($tenant) {
            RecalculateRiskScoreJob::dispatch($contract->id, $tenant->id);
        }
    }
}
