<?php

namespace App\Services;

use App\Jobs\GenerateRiskRecommendationsJob;
use App\Models\ClaimRiskScore;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\RiskEscalatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Multitenancy\Models\Tenant;

class RiskScoreService
{
    /**
     * Calcula y persiste el score de riesgo del contrato.
     * Retorna el nuevo ClaimRiskScore.
     */
    public function calculate(Contract $contract): ClaimRiskScore
    {
        $contract->load(['events', 'letters', 'changeOrders', 'milestones']);

        $factors = [
            'eventos_sin_resolver'  => $this->factorEventosSinResolver($contract),
            'cartas_vencidas'       => $this->factorCartasVencidas($contract),
            'desvio_programa'       => $this->factorDesvioPrograma($contract),
            'oc_rechazadas'         => $this->factorOcRechazadas($contract),
            'monto_disputa'         => $this->factorMontoDisputa($contract),
            'concentracion_eventos' => $this->factorConcentracionEventos($contract),
        ];

        $total = min(100, array_sum(array_column($factors, 'points')));

        $level = match (true) {
            $total <= 25  => 'bajo',
            $total <= 50  => 'medio',
            $total <= 75  => 'alto',
            default       => 'critico',
        };

        $score = ClaimRiskScore::create([
            'contract_id'   => $contract->id,
            'score_level'   => $level,
            'score_value'   => $total,
            'factors'       => $factors,
            'calculated_at' => now(),
        ]);

        // Genera recomendaciones IA y notifica cuando el nivel escala a alto o crítico
        if (in_array($level, ['alto', 'critico'])) {
            $tenant = Tenant::current();
            if ($tenant) {
                GenerateRiskRecommendationsJob::dispatch($score->id, $tenant->id);

                $recipients = User::notifiableManagers($tenant->id);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new RiskEscalatedNotification($score));
                }
            }
        }

        return $score;
    }

    /**
     * Obtiene el score vigente del contrato (el más reciente).
     */
    public function latest(Contract $contract): ?ClaimRiskScore
    {
        return ClaimRiskScore::where('contract_id', $contract->id)
            ->orderByDesc('calculated_at')
            ->first();
    }

    // -------------------------------------------------------------------------
    // Factor 1 — Eventos sin resolución > 15 días (max 20 pts)
    // -------------------------------------------------------------------------
    private function factorEventosSinResolver(Contract $contract): array
    {
        $count = $contract->events
            ->filter(fn ($e) =>
                in_array($e->resolution_status, ['pendiente', 'negociacion', 'escalado'])
                && $e->occurred_at->diffInDays(now()) > 15
            )
            ->count();

        $points = match (true) {
            $count === 0  => 0,
            $count <= 2   => 10,
            $count <= 5   => 15,
            default       => 20,
        };

        return ['label' => 'Eventos sin resolver > 15 días', 'count' => $count, 'points' => $points, 'max' => 20];
    }

    // -------------------------------------------------------------------------
    // Factor 2 — Cartas vencidas sin respuesta (max 20 pts)
    // -------------------------------------------------------------------------
    private function factorCartasVencidas(Contract $contract): array
    {
        $count = $contract->letters->where('status', 'vencida')->count();

        $points = match (true) {
            $count === 0 => 0,
            $count === 1 => 10,
            $count <= 3  => 15,
            default      => 20,
        };

        return ['label' => 'Cartas vencidas sin respuesta', 'count' => $count, 'points' => $points, 'max' => 20];
    }

    // -------------------------------------------------------------------------
    // Factor 3 — Desviación del programa (max 15 pts)
    // -------------------------------------------------------------------------
    private function factorDesvioPrograma(Contract $contract): array
    {
        $atrasados = $contract->milestones->where('status', 'atrasado')->count();

        // También considera si la fecha proyectada supera la contractual
        $diasDesvio = 0;
        if ($contract->projected_end_date && $contract->contractual_end_date) {
            $diasDesvio = max(0, $contract->contractual_end_date->diffInDays($contract->projected_end_date, false));
        }

        $points = match (true) {
            $atrasados === 0 && $diasDesvio === 0 => 0,
            $atrasados <= 2 && $diasDesvio <= 15  => 5,
            $atrasados <= 5 || $diasDesvio <= 30  => 10,
            default                                => 15,
        };

        return [
            'label'      => 'Desviación del programa',
            'atrasados'  => $atrasados,
            'dias_desvio'=> $diasDesvio,
            'points'     => $points,
            'max'        => 15,
        ];
    }

    // -------------------------------------------------------------------------
    // Factor 4 — OC rechazadas sin contraoferta (max 15 pts)
    // -------------------------------------------------------------------------
    private function factorOcRechazadas(Contract $contract): array
    {
        $count = $contract->changeOrders->where('status', 'rechazada')->count();

        $points = match (true) {
            $count === 0 => 0,
            $count === 1 => 8,
            default      => 15,
        };

        return ['label' => 'OC rechazadas sin contraoferta', 'count' => $count, 'points' => $points, 'max' => 15];
    }

    // -------------------------------------------------------------------------
    // Factor 5 — Monto en disputa como % del contrato (max 15 pts)
    // -------------------------------------------------------------------------
    private function factorMontoDisputa(Contract $contract): array
    {
        if (!$contract->current_amount || $contract->current_amount === 0) {
            return ['label' => 'Monto en disputa', 'porcentaje' => 0, 'points' => 0, 'max' => 15];
        }

        // Suma cost_impact de eventos tipo disputa + OC pendientes
        $montoEventos = $contract->events
            ->where('type', 'disputa')
            ->sum('cost_impact');

        $montoOcPendientes = $contract->changeOrders
            ->whereIn('status', ['solicitada', 'evaluacion'])
            ->sum('cost_impact');

        $total  = abs($montoEventos + $montoOcPendientes);
        $pct    = ($total / $contract->current_amount) * 100;

        $points = match (true) {
            $pct < 5   => 0,
            $pct < 10  => 8,
            default    => 15,
        };

        return [
            'label'      => 'Monto en disputa vs monto vigente',
            'porcentaje' => round($pct, 1),
            'points'     => $points,
            'max'        => 15,
        ];
    }

    // -------------------------------------------------------------------------
    // Factor 6 — Concentración de eventos en una parte (max 15 pts)
    // -------------------------------------------------------------------------
    private function factorConcentracionEventos(Contract $contract): array
    {
        $events = $contract->events;
        $total  = $events->count();

        if ($total < 3) {
            return ['label' => 'Concentración de responsabilidad', 'pct_max' => 0, 'points' => 0, 'max' => 15];
        }

        $byCounts = $events->groupBy('responsible_party')->map->count();
        $maxPct   = round(($byCounts->max() / $total) * 100, 1);

        $points = match (true) {
            $maxPct < 60 => 0,
            $maxPct < 80 => 8,
            default      => 15,
        };

        return [
            'label'   => 'Concentración de responsabilidad',
            'pct_max' => $maxPct,
            'points'  => $points,
            'max'     => 15,
        ];
    }
}
