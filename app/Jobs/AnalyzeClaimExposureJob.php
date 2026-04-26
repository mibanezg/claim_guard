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
            'events'       => fn ($q) => $q->with('costItems')->orderByDesc('occurred_at')->take(40),
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
        $eventsText = $contract->events->map(function ($e) {
            $quantumSum = $e->costItems->sum('amount');
            $line = "[{$e->occurred_at->format('d/m/Y')}] {$e->type_label} — Responsable: {$e->party_label} — Estado: {$e->resolution_label}";
            if ($quantumSum > 0) {
                $line .= " — Quantum (valor documentado): $" . number_format($quantumSum / 100, 0, ',', '.');
            } elseif ($e->cost_impact > 0) {
                $line .= " — Impacto estimado: $" . number_format($e->cost_impact / 100, 0, ',', '.');
            }
            if ($e->schedule_impact_days > 0) $line .= " — Impacto plazo: {$e->schedule_impact_days} días";
            if ($e->is_notice_overdue)         $line .= " ⚠️ AVISO VENCIDO";
            if ($e->notice_days_remaining !== null && $e->notice_days_remaining <= 3)
                $line .= " ⚠️ AVISO VENCE EN {$e->notice_days_remaining} DÍA(S)";
            $line .= "\n  Descripción: " . substr($e->description, 0, 200);
            return $line;
        })->implode("\n");

        $lettersText = $contract->letters->map(fn ($l) =>
            '[' . ($l->issued_at?->format('d/m/Y') ?? 'sin fecha') . "] {$l->letter_number} — {$l->type_label} — Estado: {$l->status_label}" .
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

        $montoOriginal    = number_format(($contract->original_amount ?? 0) / 100, 0, ',', '.');
        $montoVigente     = number_format(($contract->current_amount  ?? 0) / 100, 0, ',', '.');
        $projectedEnd     = $contract->projected_end_date?->format('d/m/Y') ?? 'No definido';
        $notificationDays = $contract->notification_days ?? 'No definido';
        $mandanteName     = $contract->mandante?->name ?? 'No registrado';
        $contractorName   = $contract->contractor?->name ?? 'No registrado';
        $startDate        = $contract->contractual_start_date?->format('d/m/Y') ?? 'No definido';
        $endDate          = $contract->contractual_end_date?->format('d/m/Y') ?? 'No definido';
        $corpusSection    = $corpusContext
            ? "EXTRACTO DEL CUERPO CONTRACTUAL:\n---\n{$corpusContext}\n---\n\n"
            : '';

        $system = <<<SYSTEM
Eres un consultor experto en claims contractuales de construcción y minería bajo legislación chilena,
con más de 20 años de experiencia en arbitrajes y mediaciones contractuales.

Tu tarea es analizar la situación contractual completa y entregar un dictamen estratégico sobre
la exposición al claim. No das consejos genéricos. Cada observación debe hacer referencia a datos
concretos del contrato: fechas específicas, montos, eventos o cartas mencionados en el contexto.

Respondes en español formal chileno.
REGLAS ESTRICTAS DE FORMATO:
1. Responde ÚNICAMENTE con el objeto JSON, sin texto antes ni después.
2. NO uses bloques de código markdown (no uses ```json ni backticks).
3. NUNCA uses saltos de línea reales dentro de los valores de string JSON. Usa \n si necesitas separar párrafos.
4. Todos los valores de texto deben estar en una sola línea continua dentro de las comillas.
SYSTEM;

        $user = <<<USER
DATOS DEL CONTRATO:
Contrato: {$contract->number} — {$contract->name}
Tipo: {$contract->type} | Moneda: {$contract->currency}
Monto original: \${$montoOriginal} | Monto vigente: \${$montoVigente}
Inicio contractual: {$startDate}
Fin contractual: {$endDate}
Fin proyectado: {$projectedEnd}
Días hábiles para notificar: {$notificationDays}
Estado actual: {$contract->status}

PARTES:
Mandante: {$mandanteName}
Contratista: {$contractorName}

CLÁUSULAS CLAVE REGISTRADAS:
{$clausesText}

{$corpusSection}

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
  "exposure_assessment": "Texto continuo en una sola línea (sin saltos de línea reales). Evalúa la exposición al claim referenciando eventos específicos, fechas y montos. Incluye estimación de entitlement y su fundamento.",
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

        $response = $ai->complete($system, $user, 4096);

        if (!$response) {
            $analysis->update([
                'status'        => 'failed',
                'error_message' => 'El servicio de IA no está disponible en este momento.',
            ]);
            Log::warning('AnalyzeClaimExposureJob: IA no disponible', ['analysis_id' => $this->analysisId]);
            return;
        }

        // Extraer y parsear JSON de la respuesta de la IA
        $data = $this->parseAiJson($response);

        if (!is_array($data)) {
            $analysis->update([
                'status'        => 'failed',
                'error_message' => 'JSON no parseable. Error: ' . json_last_error_msg() . ' | Respuesta: ' . substr($response, 0, 300),
            ]);
            Log::warning('AnalyzeClaimExposureJob: JSON no parseable', [
                'analysis_id'  => $this->analysisId,
                'json_error'   => json_last_error_msg(),
                'response_raw' => substr($response, 0, 2000),
            ]);
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

    private function parseAiJson(string $raw): ?array
    {
        // 1. Quitar fences markdown agresivamente (```json, ```)
        $text = preg_replace('/```json\s*/i', '', $raw);
        $text = preg_replace('/```/', '', $text);
        $text = trim($text);

        // 2. Extraer bloque { ... } (greedy para capturar el objeto completo)
        preg_match('/\{.*\}/s', $text, $m);
        $json = !empty($m[0]) ? $m[0] : $text;

        // 3. Reparar newlines reales dentro de strings JSON
        $json = preg_replace_callback(
            '/"((?:[^"\\\\]|\\\\.)*)"/s',
            function ($match) {
                $inner = str_replace(["\r\n", "\r", "\n"], ' ', $match[1]);
                return '"' . $inner . '"';
            },
            $json
        );

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
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
