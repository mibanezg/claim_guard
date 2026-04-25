<?php

namespace App\Services;

use App\Jobs\RecalculateRiskScoreJob;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ChangeOrderApprovedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Spatie\Multitenancy\Models\Tenant;

class ChangeOrderService
{
    public function paginate(Contract $contract, array $filters): LengthAwarePaginator
    {
        return $contract->changeOrders()
            ->when($filters['status']             ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['requested_by_party'] ?? null, fn ($q, $v) => $q->where('requested_by_party', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function create(Contract $contract, array $data, int $userId): ChangeOrder
    {
        $data['cost_impact'] = (int) round(($data['cost_impact'] ?? 0) * 100);
        $data['created_by']  = $userId;

        $order = $contract->changeOrders()->create($data);

        if (in_array($order->status, ['aprobada', 'aprobada_parcialmente'])) {
            $this->applyImpactsToContract($order);
        }

        $this->dispatchRiskRecalculation($contract->id);
        return $order;
    }

    public function update(ChangeOrder $order, array $data): ChangeOrder
    {
        $wasApproved = in_array($order->status, ['aprobada', 'aprobada_parcialmente']);

        $data['cost_impact'] = (int) round(($data['cost_impact'] ?? 0) * 100);
        $order->update($data);
        $order->refresh();

        $isNowApproved = in_array($order->status, ['aprobada', 'aprobada_parcialmente']);
        if (!$wasApproved && $isNowApproved) {
            $this->applyImpactsToContract($order);
            $this->notifyApproval($order);
        }

        $this->dispatchRiskRecalculation($order->contract_id);
        return $order;
    }

    private function dispatchRiskRecalculation(int $contractId): void
    {
        $tenant = Tenant::current();
        if ($tenant) {
            RecalculateRiskScoreJob::dispatch($contractId, $tenant->id);
        }
    }

    private function notifyApproval(ChangeOrder $order): void
    {
        $tenant = Tenant::current();
        if (!$tenant) return;

        $order->loadMissing('contract');

        $recipients = User::notifiableManagers($tenant->id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ChangeOrderApprovedNotification($order));
        }
    }

    public function delete(ChangeOrder $order): void
    {
        $order->delete();
    }

    private function applyImpactsToContract(ChangeOrder $order): void
    {
        $contract = $order->contract;

        if ($order->cost_impact !== 0) {
            $contract->increment('current_amount', $order->cost_impact);
        }

        if ($order->schedule_impact_days !== 0) {
            $baseDate = $contract->projected_end_date ?? $contract->contractual_end_date;
            if ($baseDate) {
                $contract->update([
                    'projected_end_date' => $baseDate->addDays($order->schedule_impact_days),
                ]);
            }
        }
    }

}
