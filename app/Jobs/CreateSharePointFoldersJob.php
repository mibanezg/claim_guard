<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\DocumentStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Multitenancy\Models\Tenant;

class CreateSharePointFoldersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private int $contractId,
        private int $tenantId,
    ) {}

    public function handle(DocumentStorageService $storage): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $tenant->makeCurrent();

        $contract = Contract::find($this->contractId);
        if (!$contract) return;

        $storage->createContractFolders($contract);
    }
}
