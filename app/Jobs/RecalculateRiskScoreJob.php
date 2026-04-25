<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\RiskScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Multitenancy\Models\Tenant;

class RecalculateRiskScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private int $contractId,
        private int $tenantId,
    ) {}

    public function handle(RiskScoreService $service): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $tenant->makeCurrent();

        $contract = Contract::find($this->contractId);
        if (!$contract) return;

        $service->calculate($contract);
    }
}
