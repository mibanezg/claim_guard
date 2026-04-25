<?php

namespace App\Jobs;

use App\Models\ClaimRiskScore;
use App\Services\AnthropicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class GenerateRiskRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private int $scoreId,
        private int $tenantId,
    ) {}

    public function handle(AnthropicService $ai): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $tenant->makeCurrent();

        $score = ClaimRiskScore::with('contract')->find($this->scoreId);
        if (!$score) return;

        $contract = $score->contract;

        $system = <<<SYSTEM
Eres un experto en gestión de contratos de construcción y minería en Chile,
con especialidad en prevención y resolución de claims contractuales.
Analizas indicadores de riesgo y propones acciones concretas y priorizadas
para reducir la exposición al riesgo de claim. Respondes en español formal,
de forma concisa y accionable.
SYSTEM;

        $factorsText = collect($score->factors)->map(fn ($f, $k) =>
            "- {$f['label']}: {$f['points']}/{$f['max']} pts"
        )->implode("\n");

        $user = <<<USER
Contrato: {$contract->number} — {$contract->name}
Score de riesgo actual: {$score->score_value}/100 (Nivel: {$score->score_level})

Factores activos que contribuyen al riesgo:
{$factorsText}

Genera una lista de máximo 5 recomendaciones priorizadas para reducir el riesgo de claim.
Responde SOLO con un array JSON válido de objetos, sin explicaciones adicionales.
Cada objeto debe tener exactamente estas claves:
- "title": acción corta (máx 80 caracteres)
- "detail": 2-3 oraciones explicando el POR QUÉ es urgente y CÓMO ejecutarla, citando cláusulas o plazos si aplica
- "action": uno de estos valores según el módulo más relevante: "letters", "events", "change-orders", "milestones", "expediente"
- "action_label": texto corto del botón (ej: "Ir a Cartas", "Ver Eventos", "Ver OC")

Ejemplo de formato esperado:
[{"title":"Emitir carta de reserva de derechos","detail":"Explicación detallada aquí.","action":"letters","action_label":"Ir a Cartas"}]
USER;

        $response = $ai->message($system, $user, 512);

        if (!$response) {
            Log::warning('GenerateRiskRecommendationsJob: IA no disponible', ['score_id' => $this->scoreId]);
            return;
        }

        // Extrae el array JSON de la respuesta
        preg_match('/\[.*\]/s', $response, $matches);
        if (empty($matches[0])) {
            Log::warning('GenerateRiskRecommendationsJob: respuesta no parseable', ['response' => $response]);
            return;
        }

        $recommendations = json_decode($matches[0], true);
        if (!is_array($recommendations)) return;

        // Normalizar: si vienen strings simples (formato antiguo), convertir a objetos
        $recommendations = array_map(fn ($r) => is_string($r)
            ? ['title' => $r, 'detail' => '', 'action' => 'events', 'action_label' => 'Ver Eventos']
            : $r,
        $recommendations);

        $score->update(['recommendations' => $recommendations]);

        Log::info('GenerateRiskRecommendationsJob: recomendaciones generadas', [
            'score_id' => $this->scoreId,
            'count'    => count($recommendations),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateRiskRecommendationsJob: falló tras reintentos', [
            'score_id' => $this->scoreId,
            'error'    => $e->getMessage(),
        ]);
    }
}
