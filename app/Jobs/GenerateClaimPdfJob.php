<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\DocumentStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateClaimPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        private int $contractId,
        private int $tenantId,
    ) {}

    public function handle(DocumentStorageService $storage): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $tenant->makeCurrent();

        $contract = Contract::with([
            'mandante',
            'contractor',
            'events'             => fn ($q) => $q->orderBy('occurred_at'),
            'events.costItems',
            'events.delayAnalysis',
            'letters'            => fn ($q) => $q->orderBy('issued_at'),
            'changeOrders',
            'milestones',
            'documents',
            'latestRiskScore',
        ])->find($this->contractId);

        if (!$contract) return;

        $slug     = $tenant->slug ?? 'default';
        $filename = "expediente-claim-{$contract->number}-" . now()->format('Ymd-His') . '.pdf';
        $localDir = storage_path("app/tenants/{$slug}/Expediente");
        $localPath = "{$localDir}/{$filename}";

        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        // Convierte el Markdown del resumen a HTML antes de pasarlo a la vista
        $summaryHtml = null;
        if ($contract->claim_summary) {
            $env = new \League\CommonMark\Environment\Environment([
                'html_input'        => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $env->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
            $env->addExtension(new \League\CommonMark\Extension\Table\TableExtension());
            $converter  = new \League\CommonMark\MarkdownConverter($env);
            $summaryHtml = $converter->convert($contract->claim_summary)->getContent();
        }

        try {
            Pdf::loadView('pdf.claim-expediente', [
                'contract'    => $contract,
                'riskScore'   => $contract->latestRiskScore,
                'summaryHtml' => $summaryHtml,
            ])
            ->setPaper('a4')
            ->save($localPath);
        } catch (\Throwable $e) {
            Log::error('GenerateClaimPdfJob: error generando PDF', [
                'contract_id' => $this->contractId,
                'error'       => $e->getMessage(),
            ]);
            return;
        }

        // Intenta subir a SharePoint si está configurado
        $sharepointId  = null;
        $sharepointUrl = null;

        if ($storage->isSharePointConfigured()) {
            try {
                $remotePath = "ClaimGuard/{$slug}/{$contract->number}/Expediente/{$filename}";
                $result = app(\App\Services\MicrosoftGraphService::class)->uploadFile($localPath, $remotePath);
                $sharepointId  = $result['sharepoint_id']  ?? null;
                $sharepointUrl = $result['sharepoint_url'] ?? null;

                // Si se subió a SharePoint, elimina el temporal local
                if ($sharepointId) {
                    @unlink($localPath);
                    $localPath = null;
                }
            } catch (\Throwable $e) {
                Log::warning('GenerateClaimPdfJob: error subiendo a SharePoint, PDF guardado local', [
                    'contract_id' => $this->contractId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $contract->update([
            'claim_pdf_path'             => $localPath ? "tenants/{$slug}/Expediente/{$filename}" : null,
            'claim_pdf_sharepoint_id'    => $sharepointId,
            'claim_pdf_sharepoint_url'   => $sharepointUrl,
            'claim_generated_at'         => now(),
        ]);

        Log::info('GenerateClaimPdfJob: PDF generado', [
            'contract_id'   => $this->contractId,
            'sharepoint'    => !is_null($sharepointId),
            'local'         => !is_null($localPath),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateClaimPdfJob: falló tras reintentos', [
            'contract_id' => $this->contractId,
            'error'       => $e->getMessage(),
        ]);
    }
}
