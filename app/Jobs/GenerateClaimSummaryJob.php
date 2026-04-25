<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class GenerateClaimSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private int $contractId,
        private int $tenantId,
    ) {}

    public function handle(AiService $ai): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $tenant->makeCurrent();

        $contract = Contract::with([
            'mandante',
            'contractor',
            'events'            => fn ($q) => $q->orderBy('occurred_at'),
            'events.costItems',
            'events.delayAnalysis',
            'letters'           => fn ($q) => $q->orderBy('issued_at'),
            'changeOrders',
            'milestones',
            'latestRiskScore',
        ])->find($this->contractId);

        if (!$contract) return;

        $system = <<<SYSTEM
Eres un experto en gestión de contratos de construcción y minería en Chile,
especializado en la preparación de expedientes de claim contractual.
Redactas en español formal chileno con precisión técnica-legal.

REGLAS DE FORMATO OBLIGATORIAS:
- Usa SOLO texto plano, sin Markdown.
- NO uses #, ##, **, *, |, ---, ni ningún símbolo de formato.
- NO incluyas tablas ni listas con guiones.
- Separa los párrafos con una línea en blanco.
- Cada sección va en párrafos narrativos continuos.
- El PDF ya contiene las tablas de eventos, cartas y órdenes de cambio.
  Tu tarea es escribir SOLO el texto narrativo del resumen ejecutivo.
SYSTEM;

        $eventsText = $contract->events->map(fn ($e) =>
            "- {$e->occurred_at->format('d/m/Y')} [{$e->type}] {$e->description} (Responsable: {$e->responsible_party}, Estado: {$e->resolution_status})"
        )->implode("\n");

        $lettersText = $contract->letters->map(fn ($l) =>
            "- {$l->letter_number} [{$l->type}] {$l->subject} — Estado: {$l->status}"
        )->implode("\n");

        $changeOrdersText = $contract->changeOrders->map(fn ($oc) =>
            "- {$oc->request_number}: {$oc->description} — Impacto: {$oc->cost_impact} / {$oc->schedule_impact_days} días — Estado: {$oc->status}"
        )->implode("\n");

        $scoreLevel      = $contract->latestRiskScore?->score_level ?? 'no calculado';
        $scoreValue      = $contract->latestRiskScore?->score_value ?? 'N/A';
        $mandante        = $contract->mandante?->name ?? '—';
        $contractor      = $contract->contractor?->name ?? '—';
        $startDate       = $contract->contractual_start_date?->format('d/m/Y') ?? '—';
        $endDate         = $contract->contractual_end_date?->format('d/m/Y') ?? '—';
        $projectedDate   = $contract->projected_end_date?->format('d/m/Y') ?? 'No proyectada';

        // Contexto quantum
        $quantumText = $contract->events
            ->filter(fn ($e) => $e->costItems->isNotEmpty())
            ->map(fn ($e) => sprintf(
                '- Evento %s (%s): quantum total %s %s (directo: %s, indirecto: %s)',
                $e->type, $e->occurred_at->format('d/m/Y'),
                $contract->currency, number_format($e->costItems->sum('amount') / 100, 0, ',', '.'),
                number_format($e->costItems->whereIn('cost_category', ['mano_obra_directa','materiales','equipos','subcontratos'])->sum('amount') / 100, 0, ',', '.'),
                number_format($e->costItems->whereIn('cost_category', ['gastos_obra','overhead_sede'])->sum('amount') / 100, 0, ',', '.')
            ))->implode("\n") ?: 'Sin quantum documentado';

        $totalQuantum = $contract->events->flatMap->costItems->sum('amount');

        // Contexto CPM
        $cpmText = $contract->events
            ->filter(fn ($e) => !is_null($e->delayAnalysis))
            ->map(fn ($e) => sprintf(
                '- Evento %s (%s): tipo %s, método %s, %d días, ruta crítica: %s',
                $e->type, $e->occurred_at->format('d/m/Y'),
                $e->delayAnalysis->delay_type,
                $e->delayAnalysis->analysis_method,
                $e->delayAnalysis->delay_days ?? 0,
                $e->delayAnalysis->is_critical_path ? 'SÍ' : 'no'
            ))->implode("\n") ?: 'Sin análisis CPM documentado';

        $user = <<<USER
EXPEDIENTE DE CLAIM — DATOS DEL CONTRATO

Contrato: {$contract->number} — {$contract->name}
Mandante: {$mandante}
Contratista: {$contractor}
Tipo: {$contract->type}
Monto original: {$contract->original_amount} (centavos CLP)
Monto vigente: {$contract->current_amount} (centavos CLP)
Fecha inicio contractual: {$startDate}
Fecha término contractual: {$endDate}
Fecha término proyectada: {$projectedDate}
Indicador de riesgo: {$scoreLevel} ({$scoreValue}/100)

EVENTOS CONTRACTUALES ({$contract->events->count()} total):
{$eventsText}

CORRESPONDENCIA ({$contract->letters->count()} cartas):
{$lettersText}

ÓRDENES DE CAMBIO ({$contract->changeOrders->count()} total):
{$changeOrdersText}

QUANTUM DE COSTOS DOCUMENTADO ({$contract->currency} {$totalQuantum}):
{$quantumText}

ANÁLISIS DE PLAZO CPM:
{$cpmText}

Redacta el RESUMEN EJECUTIVO del expediente de claim. El documento ya incluye secciones separadas con tablas detalladas de eventos, cartas y órdenes de cambio, por lo tanto NO las repitas aquí.

Estructura el resumen en exactamente cuatro secciones con estos encabezados en mayúsculas seguidos de dos puntos:

ANTECEDENTES DEL CONTRATO:
Describe el objeto del contrato, las partes, el monto y los plazos contractuales. 2 párrafos narrativos.

SITUACIÓN CONTRACTUAL Y HECHOS RELEVANTES:
Describe en lenguaje narrativo los principales hechos que dan origen al claim, sin listar fechas ni tablas. Menciona el impacto acumulado total en plazo y costo. 2-3 párrafos.

POSICIÓN CONTRACTUAL DEL CONTRATISTA:
Fundamenta jurídicamente la posición del contratista, citando los tipos de eventos y su imputabilidad. 1-2 párrafos.

CONCLUSIONES Y RECOMENDACIONES:
Señala las acciones recomendadas para el proceso de negociación o arbitraje. 1 párrafo.

El indicador de riesgo de claim es: {$scoreLevel} ({$scoreValue}/100). Menciona esto en las conclusiones.
El expediente incluye secciones separadas de Quantum de Costos y Análisis CPM, por lo tanto en el resumen menciona los totales globales sin repetir el detalle ítem por ítem.
El texto debe ser objetivo, factual y útil para un árbitro o mediador. Solo texto, sin Markdown.
USER;

        $summary = $ai->complete($system, $user, 4096);

        if (!$summary) {
            Log::warning('GenerateClaimSummaryJob: IA no disponible', ['contract_id' => $this->contractId]);
            // Guardamos un marcador para que el job de PDF continúe sin resumen IA
            $contract->update(['claim_summary' => null]);
            return;
        }

        $contract->update(['claim_summary' => $summary]);

        Log::info('GenerateClaimSummaryJob: resumen generado', ['contract_id' => $this->contractId]);

        // Dispara la generación del PDF
        $tenant = Tenant::current();
        GenerateClaimPdfJob::dispatch($this->contractId, $tenant->id);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateClaimSummaryJob: falló tras reintentos', [
            'contract_id' => $this->contractId,
            'error'       => $e->getMessage(),
        ]);
    }
}
