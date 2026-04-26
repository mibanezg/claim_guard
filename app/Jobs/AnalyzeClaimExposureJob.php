<?php

namespace App\Jobs;

use App\Models\ContractAiAnalysis;
use App\Models\ContractDocument;
use App\Services\AiService;
use App\Services\ContractPdfService;
use App\Services\DailyReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class AnalyzeClaimExposureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;
    public int $timeout = 180;

    public function __construct(
        private int $analysisId,
        private int $tenantId,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $tenant->makeCurrent();

        // Instanciar servicios DESPUÉS de makeCurrent() para que lean la
        // integración correcta del tenant (en async queue no hay tenant activo al inicio)
        $ai                 = app(AiService::class);
        $dailyReportService = app(DailyReportService::class);

        $analysis = ContractAiAnalysis::find($this->analysisId);
        if (!$analysis) return;

        $analysis->update(['status' => 'processing']);

        $contract = $analysis->contract()->with([
            'mandante',
            'contractor',
            'events'       => fn ($q) => $q->orderByDesc('occurred_at')->take(40),
            'letters'      => fn ($q) => $q->orderByDesc('issued_at')->take(30),
            'changeOrders' => fn ($q) => $q->orderByDesc('created_at')->take(20),
            'latestRiskScore',
            'dailyReports' => fn ($q) => $q->orderByDesc('report_date')->take(10),
        ])->first();

        if (!$contract) {
            $analysis->update(['status' => 'failed', 'error_message' => 'Contrato no encontrado.']);
            return;
        }

        $missingDays = $dailyReportService->missingDays($contract);

        // Construir secciones del contexto
        $eventsText = $contract->events->map(fn ($e) =>
            "[{$e->occurred_at->format('d/m/Y')}] {$e->type_label} — Responsable: {$e->party_label} — Estado: {$e->resolution_label}" .
            ($e->cost_impact > 0 ? " — Impacto costo: $" . number_format($e->cost_impact / 100, 0, ',', '.') : '') .
            ($e->schedule_impact_days > 0 ? " — Impacto plazo: {$e->schedule_impact_days} días" : '') .
            ($e->is_notice_overdue ? " ⚠️ AVISO VENCIDO" : '') .
            ($e->notice_days_remaining !== null && $e->notice_days_remaining <= 3 ? " ⚠️ AVISO VENCE EN {$e->notice_days_remaining} DÍA(S)" : '') .
            "\n  Descripción: " . substr($e->description, 0, 200)
        )->implode("\n");

        $lettersText = $contract->letters->map(fn ($l) =>
            "[{$l->issued_at?->format('d/m/Y') ?? 'sin fecha'}] {$l->letter_number} — {$l->type_label} — Estado: {$l->status_label}" .
            ($l->response_deadline && $l->status === 'vencida' ? " ⚠️ VENCIDA sin respuesta" : '') .
            "\n  Asunto: {$l->subject}"
        )->implode("\n") ?: 'Sin correspondencia registrada.';

        $changeOrdersText = $contract->changeOrders->map(fn ($oc) =>
            "[{$oc->created_at->format('d/m/Y')}] {$oc->request_number} — Solicitante: {$oc->requested_by_party} — Estado: {$oc->status}" .
            " — Impacto: $" . number_format(($oc->cost_impact ?? 0) / 100, 0, ',', '.') . " / {$oc->schedule_impact_days} días"
        )->implode("\n") ?: 'Sin órdenes de cambio registradas.';

        $riskScore = $contract->latestRiskScore;
        $riskText = $riskScore
            ? "Score: {$riskScore->score_value}/100 (Nivel: {$riskScore->score_level})\n" .
              collect($riskScore->factors ?? [])->map(fn ($f) => "  - {$f['label']}: {$f['points']}/{$f['max']} pts")->implode("\n")
            : 'Sin score calculado.';

        $dailyText = "Total registrados: {$contract->dailyReports->count()}\n" .
            "Días sin reporte (últimos 60): " . count($missingDays) . "\n" .
            "Reportes con instrucciones mandante: " . $contract->dailyReports->where('instructions_received', '!=', '')->count();

        // Cláusulas del contrato
        $clausesText = $contract->clauses
            ? collect($contract->clauses)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n")
            : 'Sin cláusulas registradas.';

        // Cuerpo contractual si está disponible
        $corpusDocs = ContractDocument::where('contract_id', $contract->id)->constitutive()->get();
        $corpusContext = ContractPdfService::buildCorpusContext($corpusDocs, 8_000);
        if (!$corpusContext && $contract->contract_text) {
            $corpusContext = ContractPdfService::forPrompt($contract->contract_text, 10_000);
        }

        $montoOriginal = number_format(($contract->original_amount ?? 0) / 100, 0, ',', '.');
        $montoVigente  = number_format(($contract->current_amount  ?? 0) / 100, 0, ',', '.');

        $system = <<<SYSTEM
Eres un consultor experto en claims contractuales de construcción y minería bajo legislación chilena,
con más de 20 años de experiencia en arbitrajes y mediaciones contractuales.

Tu tarea es analizar la situación contractual completa y entregar un dictamen estratégico sobre
la exposición al claim. No das consejos genéricos. Cada observación debe hacer referencia a datos
concretos del contrato: fechas específicas, montos, eventos o cartas mencionados en el contexto.

Respondes en español formal chileno. Responde ÚNICAMENTE con un objeto JSON válido, sin texto previo ni posterior.
SYSTEM;

        $user = <<<USER
DATOS DEL CONTRATO:
Contrato: {$contract->number} — {$contract->name}
Tipo: {$contract->type} | Moneda: {$contract->currency}
Monto original: \${$montoOriginal} | Monto vigente: \${$montoVigente}
Inicio contractual: {$contract->contractual_start_date?->format('d/m/Y')}
Fin contractual: {$contract->contractual_end_date?->format('d/m/Y')}
Fin proyectado: {$contract->projected_end_date?->format('d/m/Y') ?? 'No definido'}
Días hábiles para notificar: {$contract->notification_days ?? 'No definido'}
Estado actual: {$contract->status}

PARTES:
Mandante: {$contract->mandante?->name ?? 'No registrado'}
Contratista: {$contract->contractor?->name ?? 'No registrado'}

CLÁUSULAS CLAVE REGISTRADAS:
{$clausesText}

{$corpusContext ? "EXTRACTO DEL CUERPO CONTRACTUAL:\n---\n{$corpusContext}\n---\n\n" : ''}

EVENTOS CONTRACTUALES ({$contract->events->count()} registrados, mostrando últimos 40):
{$eventsText}

CORRESPONDENCIA ({$contract->letters->count()} cartas, mostrando últimas 30):
{$lettersText}

ÓRDENES DE CAMBIO ({$contract->changeOrders->count()} OC, mostrando últimas 20):
{$changeOrdersText}

DIARIO DE OBRA:
{$dailyText}

INDICADOR DE RIESGO ACTUAL:
{$riskText}

Con base en todos los datos anteriores, entrega tu dictamen estratégico en el siguiente JSON exacto:

{
  "exposure_assessment": "Evaluación narrativa de la exposición al claim. 3-5 párrafos. Referencia eventos específicos, fechas y montos concretos. Incluye una estimación de si hay entitlement para un claim y en qué se funda.",
  "strong_points": [
    "Punto fuerte concreto con referencia a datos específicos",
    "..."
  ],
  "weak_points": [
    "Debilidad o riesgo concreto con referencia a datos específicos",
    "..."
  ],
  "urgent_actions": [
    {
      "action": "Descripción específica de la acción a tomar",
      "priority": "alta",
      "deadline": "YYYY-MM-DD o null si no hay plazo específico",
      "reason": "Por qué es urgente en 1-2 oraciones"
    }
  ],
  "pattern_observations": [
    "Patrón detectado en el comportamiento contractual con referencia a datos",
    "..."
  ],
  "key_clauses": [
    "Cláusula o mecanismo contractual relevante para el claim",
    "..."
  ],
  "estimated_exposure_days": 0,
  "estimated_exposure_cost": 0,
  "analysis_confidence": "alta",
  "confidence_note": "Razón del nivel de confianza: qué datos hay disponibles o qué falta para una evaluación más precisa"
}
USER;

        $response = $ai->complete($system, $user, 3000);

        if (!$response) {
            $analysis->update([
                'status'        => 'failed',
                'error_message' => 'El servicio de IA no está disponible en este momento.',
            ]);
            Log::warning('AnalyzeClaimExposureJob: IA no disponible', ['analysis_id' => $this->analysisId]);
            return;
        }

        // Extraer JSON de la respuesta
        preg_match('/\{.*\}/s', $response, $matches);
        if (empty($matches[0])) {
            $analysis->update([
                'status'        => 'failed',
                'error_message' => 'La respuesta de IA no pudo ser procesada. Intenta nuevamente.',
            ]);
            Log::warning('AnalyzeClaimExposureJob: respuesta no parseable', ['response' => substr($response, 0, 500)]);
            return;
        }

        $data = json_decode($matches[0], true);
        if (!is_array($data)) {
            $analysis->update(['status' => 'failed', 'error_message' => 'JSON inválido en respuesta de IA.']);
            return;
        }

        $analysis->update([
            'status'               => 'completed',
            'exposure_assessment'  => $data['exposure_assessment'] ?? null,
            'strong_points'        => $data['strong_points'] ?? [],
            'weak_points'          => $data['weak_points'] ?? [],
            'urgent_actions'       => $data['urgent_actions'] ?? [],
            'pattern_observations' => $data['pattern_observations'] ?? [],
            'key_clauses'          => $data['key_clauses'] ?? [],
            'estimated_exposure_days' => (int) ($data['estimated_exposure_days'] ?? 0),
            'estimated_exposure_cost' => (int) round(($data['estimated_exposure_cost'] ?? 0) * 100),
            'analysis_confidence'  => $data['analysis_confidence'] ?? 'media',
            'confidence_note'      => $data['confidence_note'] ?? null,
            'completed_at'         => now(),
        ]);

        Log::info('AnalyzeClaimExposureJob: análisis completado', ['analysis_id' => $this->analysisId]);
    }

    public function failed(\Throwable $e): void
    {
        $analysis = ContractAiAnalysis::find($this->analysisId);
        $analysis?->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        Log::error('AnalyzeClaimExposureJob: falló tras reintentos', [
            'analysis_id' => $this->analysisId,
            'error'       => $e->getMessage(),
        ]);
    }
}
