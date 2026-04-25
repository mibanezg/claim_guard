<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractualEvent;
use Inertia\Inertia;
use Inertia\Response;

class ClaimStatusController extends Controller
{
    public function show(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load(['mandante', 'contractor']);

        // Eventos con todos los indicadores de completitud
        $events = $contract->events()
            ->withCount(['costItems', 'rightsLetter'])
            ->with('delayAnalysis')
            ->orderByDesc('occurred_at')
            ->get()
            ->map(function ($e) {
                $hasQuantum  = $e->cost_items_count > 0;
                $hasCpm      = !is_null($e->delayAnalysis);
                $rightsStatus = $this->resolveRightsStatus($e);
                $noticeStatus = $this->resolveNoticeStatus($e);

                return [
                    'id'                   => $e->id,
                    'type_label'           => $e->type_label,
                    'occurred_at'          => $e->occurred_at?->format('d/m/Y'),
                    'description'          => $e->description,
                    'responsible_party'    => $e->responsible_party,
                    'party_label'          => $e->party_label,
                    'schedule_impact_days' => $e->schedule_impact_days,
                    'cost_impact'          => $e->cost_impact / 100,
                    'resolution_status'    => $e->resolution_status,
                    // Indicadores
                    'has_quantum'          => $hasQuantum,
                    'quantum_total'        => $e->costItems()->sum('amount') / 100,
                    'has_cpm'              => $hasCpm,
                    'cpm_delay_type'       => $e->delayAnalysis?->delay_type_label,
                    'cpm_days'             => $e->delayAnalysis?->delay_days,
                    'rights_status'        => $rightsStatus,
                    'rights_letters_count' => $e->rights_letter_count,
                    'notice_status'        => $noticeStatus,
                    'notice_deadline'      => $e->notice_deadline?->format('d/m/Y'),
                    // Score de completitud (cuántos de los indicadores aplican y están OK)
                    'completeness_score'   => $this->computeScore($e, $hasQuantum, $hasCpm, $rightsStatus, $noticeStatus),
                    'completeness_total'   => $this->computeTotal($e),
                ];
            });

        // Cartas vencidas sin respuesta
        $expiredLetters = $contract->letters()
            ->where('status', 'vencida')
            ->get()
            ->map(fn ($l) => [
                'id'              => $l->id,
                'letter_number'   => $l->letter_number,
                'subject'         => $l->subject,
                'response_deadline' => $l->response_deadline?->format('d/m/Y'),
                'type'            => $l->type,
            ]);

        // Totales para las stat cards
        $total        = $events->count();
        $fullyReady   = $events->filter(fn ($e) => $e['completeness_score'] === $e['completeness_total'])->count();
        $withQuantum  = $events->where('has_quantum', true)->count();
        $withCpm      = $events->where('has_cpm', true)->where('schedule_impact_days', '>', 0)->count();
        $cpmRequired  = $events->where('schedule_impact_days', '>', 0)->count();
        $rightsOk     = $events->whereIn('rights_status', ['formal', 'na'])->count();
        $rightsCritical = $events->where('rights_status', 'none')->count();

        return Inertia::render('Contracts/ClaimStatus', [
            'contract'       => ContractResource::make($contract)->resolve(),
            'events'         => $events->values()->toArray(),
            'expiredLetters' => $expiredLetters->toArray(),
            'summary'        => [
                'total'           => $total,
                'fully_ready'     => $fullyReady,
                'with_quantum'    => $withQuantum,
                'without_quantum' => $total - $withQuantum,
                'with_cpm'        => $withCpm,
                'cpm_required'    => $cpmRequired,
                'rights_ok'       => $rightsOk,
                'rights_critical' => $rightsCritical,
                'expired_letters' => $expiredLetters->count(),
            ],
            'flash'          => session()->only(['success', 'error']),
        ]);
    }

    private function resolveRightsStatus($event): string
    {
        if ($event->responsible_party === 'contratista') return 'na';
        if (!$event->rights_reserved) return 'none';
        return $event->rights_letter_count > 0 ? 'formal' : 'informal';
    }

    private function resolveNoticeStatus($event): string
    {
        if (!$event->notice_deadline) return 'na';
        return match ($event->notification_status) {
            'notified'    => 'ok',
            'not_required'=> 'na',
            default       => $event->is_notice_overdue ? 'overdue' : 'pending',
        };
    }

    private function computeScore($event, bool $hasQuantum, bool $hasCpm, string $rights, string $notice): int
    {
        $score = 0;
        if ($hasQuantum) $score++;
        if ($event->schedule_impact_days > 0 && $hasCpm) $score++;
        if (in_array($rights, ['formal', 'na'])) $score++;
        if (in_array($notice, ['ok', 'na'])) $score++;
        return $score;
    }

    private function computeTotal($event): int
    {
        // Cuántos indicadores aplican a este evento
        $total = 2; // quantum + rights siempre aplican
        if ($event->schedule_impact_days > 0) $total++; // CPM solo si tiene impacto
        if ($event->notice_deadline) $total++;           // Aviso solo si tiene plazo
        return $total;
    }
}
