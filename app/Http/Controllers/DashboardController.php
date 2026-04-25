<?php

namespace App\Http\Controllers;

use App\Models\ChangeOrder;
use App\Models\ClaimRiskScore;
use App\Models\Contract;
use App\Models\ContractLetter;
use App\Models\ContractMilestone;
use App\Models\ContractualEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Determina si el usuario ve vista ejecutiva o vista operativa
        $isExecutive = $user->hasAnyRole(['super_admin', 'tenant_admin', 'manager']);

        if ($isExecutive) {
            return $this->executiveDashboard();
        }

        return $this->operativeDashboard($user);
    }

    // -------------------------------------------------------------------------
    // Vista ejecutiva (tenant_admin / manager / super_admin)
    // -------------------------------------------------------------------------
    private function executiveDashboard(): Response
    {
        $contracts = Contract::with(['mandante', 'contractor', 'latestRiskScore'])
            ->whereIn('status', ['vigente', 'suspendido', 'en_disputa'])
            ->orderByDesc('updated_at')
            ->get();

        // KPIs superiores
        $clpContracts = $contracts->where('currency', 'CLP');
        $usdContracts = $contracts->where('currency', 'USD');

        $kpis = [
            'contratos_activos'   => $contracts->count(),
            'monto_clp'           => $clpContracts->sum('current_amount'),  // centavos
            'monto_usd'           => $usdContracts->sum('current_amount'),  // centavos
            'contratos_disputa'   => $contracts->where('status', 'en_disputa')->count(),
            'oc_pendientes'       => ChangeOrder::whereIn('status', ['solicitada', 'evaluacion'])->count(),
            'cartas_vencidas'     => ContractLetter::where('status', 'vencida')->count(),
            'eventos_sin_resolver'=> ContractualEvent::whereIn('resolution_status', ['pendiente', 'negociacion', 'escalado'])
                ->where('occurred_at', '<=', now()->subDays(15))
                ->count(),
        ];

        // Distribución de riesgo
        $riskDist = [
            'critico' => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'critico')->count(),
            'alto'    => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'alto')->count(),
            'medio'   => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'medio')->count(),
            'bajo'    => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'bajo')->count(),
            'sin_score' => $contracts->filter(fn ($c) => !$c->latestRiskScore)->count(),
        ];

        // Tabla: contratos con semáforo de riesgo
        $contractList = $contracts->map(fn ($c) => [
            'id'           => $c->id,
            'name'         => $c->name,
            'number'       => $c->number,
            'status'       => $c->status,
            'status_label' => Contract::STATUS_LABELS[$c->status] ?? $c->status,
            'mandante'     => $c->mandante?->name,
            'contractor'   => $c->contractor?->name,
            'currency'     => $c->currency,
            'current_amount' => $c->current_amount,
            'risk_level'   => $c->latestRiskScore?->score_level,
            'risk_value'   => $c->latestRiskScore?->score_value,
        ]);

        // Cartas vencidas agrupadas por contrato (hasta 5)
        $expiredLetters = ContractLetter::with('contract')
            ->where('status', 'vencida')
            ->orderByDesc('response_deadline')
            ->take(8)
            ->get()
            ->map(fn ($l) => [
                'id'             => $l->id,
                'letter_number'  => $l->letter_number,
                'subject'        => $l->subject,
                'contract_name'  => $l->contract?->name,
                'contract_id'    => $l->contract_id,
                'response_deadline' => $l->response_deadline?->format('d/m/Y'),
            ]);

        // OC pendientes de aprobación (hasta 5)
        $pendingOc = ChangeOrder::with('contract')
            ->whereIn('status', ['solicitada', 'evaluacion'])
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn ($oc) => [
                'id'             => $oc->id,
                'request_number' => $oc->request_number,
                'description'    => \Str::limit($oc->description, 60),
                'contract_name'  => $oc->contract?->name,
                'contract_id'    => $oc->contract_id,
                'cost_impact'    => $oc->cost_impact,
                'currency'       => $oc->contract?->currency ?? 'CLP',
                'status'         => $oc->status,
                'status_label'   => ChangeOrder::STATUS_LABELS[$oc->status] ?? $oc->status,
            ]);

        // Gráfico: historial de risk score últimos 90 días (agrupado por día, promedio)
        $riskHistory = $this->buildRiskHistory();

        return Inertia::render('Dashboard', [
            'mode'          => 'executive',
            'kpis'          => $kpis,
            'risk_dist'     => $riskDist,
            'contract_list' => $contractList,
            'expired_letters' => $expiredLetters,
            'pending_oc'    => $pendingOc,
            'risk_history'  => $riskHistory,
            'flash'         => session()->only(['success', 'error']),
        ]);
    }

    // -------------------------------------------------------------------------
    // Vista operativa (contract_admin / field_engineer / legal)
    // -------------------------------------------------------------------------
    private function operativeDashboard($user): Response
    {
        // Contratos asignados al usuario (via contract_users)
        $assignedIds = \DB::connection('tenant')
            ->table('contract_users')
            ->where('user_id', $user->id)
            ->pluck('contract_id');

        $myContracts = Contract::with(['mandante', 'latestRiskScore'])
            ->when($assignedIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $assignedIds))
            ->whereIn('status', ['vigente', 'suspendido', 'en_disputa'])
            ->get();

        $contractIds = $myContracts->pluck('id');

        // Hitos próximos 14 días
        $upcomingMilestones = ContractMilestone::with('contract')
            ->whereIn('contract_id', $contractIds)
            ->whereIn('status', ['pendiente', 'en_progreso'])
            ->whereBetween('planned_date', [now(), now()->addDays(14)])
            ->orderBy('planned_date')
            ->take(6)
            ->get()
            ->map(fn ($m) => [
                'id'            => $m->id,
                'name'          => $m->name,
                'planned_date'  => $m->planned_date->format('d/m/Y'),
                'days_left'     => now()->diffInDays($m->planned_date, false),
                'contract_name' => $m->contract?->name,
                'contract_id'   => $m->contract_id,
                'is_critical'   => $m->is_critical,
            ]);

        // Eventos pendientes de documentar (sin documentos asociados, pendientes)
        $pendingEvents = ContractualEvent::with('contract')
            ->whereIn('contract_id', $contractIds)
            ->whereIn('resolution_status', ['pendiente', 'negociacion'])
            ->orderByDesc('occurred_at')
            ->take(5)
            ->get()
            ->map(fn ($e) => [
                'id'            => $e->id,
                'type'          => $e->type,
                'type_label'    => ContractualEvent::TYPE_LABELS[$e->type] ?? $e->type,
                'occurred_at'   => $e->occurred_at->format('d/m/Y'),
                'description'   => \Str::limit($e->description, 70),
                'contract_name' => $e->contract?->name,
                'contract_id'   => $e->contract_id,
                'days_old'      => $e->occurred_at->diffInDays(now()),
            ]);

        // Cartas con respuesta pendiente esta semana
        $lettersThisWeek = ContractLetter::with('contract')
            ->whereIn('contract_id', $contractIds)
            ->whereIn('status', ['emitida', 'recibida'])
            ->where('response_deadline', '<=', now()->addDays(7))
            ->orderBy('response_deadline')
            ->take(5)
            ->get()
            ->map(fn ($l) => [
                'id'             => $l->id,
                'letter_number'  => $l->letter_number,
                'subject'        => \Str::limit($l->subject, 60),
                'contract_name'  => $l->contract?->name,
                'contract_id'    => $l->contract_id,
                'response_deadline' => $l->response_deadline?->format('d/m/Y'),
                'days_left'      => $l->response_deadline ? now()->diffInDays($l->response_deadline, false) : null,
            ]);

        $contractList = $myContracts->map(fn ($c) => [
            'id'           => $c->id,
            'name'         => $c->name,
            'number'       => $c->number,
            'status'       => $c->status,
            'status_label' => Contract::STATUS_LABELS[$c->status] ?? $c->status,
            'mandante'     => $c->mandante?->name,
            'risk_level'   => $c->latestRiskScore?->score_level,
            'risk_value'   => $c->latestRiskScore?->score_value,
        ]);

        return Inertia::render('Dashboard', [
            'mode'               => 'operative',
            'contract_list'      => $contractList,
            'upcoming_milestones'=> $upcomingMilestones,
            'pending_events'     => $pendingEvents,
            'letters_this_week'  => $lettersThisWeek,
            'flash'              => session()->only(['success', 'error']),
        ]);
    }

    // -------------------------------------------------------------------------
    // Historial de riesgo promedio: últimos 90 días, un punto por día
    // -------------------------------------------------------------------------
    private function buildRiskHistory(): array
    {
        $from = now()->subDays(89)->startOfDay();

        $scores = ClaimRiskScore::where('calculated_at', '>=', $from)
            ->orderBy('calculated_at')
            ->get(['score_value', 'score_level', 'calculated_at']);

        if ($scores->isEmpty()) {
            return ['labels' => [], 'data' => []];
        }

        // Agrupa por fecha (dd/mm) y promedia
        $grouped = $scores->groupBy(fn ($s) => $s->calculated_at->format('d/m'));

        $labels = [];
        $data   = [];

        foreach ($grouped as $date => $group) {
            $labels[] = $date;
            $data[]   = round($group->avg('score_value'), 1);
        }

        return compact('labels', 'data');
    }
}
