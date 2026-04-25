<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClaimRiskScoreResource;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ClaimRiskScore;
use App\Services\RiskScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RiskController extends Controller
{
    public function __construct(private RiskScoreService $service) {}

    public function index(Request $request): Response
    {
        $contracts = Contract::with(['mandante', 'contractor', 'latestRiskScore'])
            ->whereIn('status', ['vigente', 'suspendido', 'en_disputa'])
            ->orderByDesc('updated_at')
            ->get();

        $contractsData = $contracts->map(function (Contract $contract) {
            $score = $contract->latestRiskScore;

            return [
                'id'             => $contract->id,
                'name'           => $contract->name,
                'number'         => $contract->number,
                'status'         => $contract->status,
                'status_label'   => Contract::STATUS_LABELS[$contract->status] ?? $contract->status,
                'mandante'       => $contract->mandante?->name,
                'contractor'     => $contract->contractor?->name,
                'risk_score'     => $score ? (new ClaimRiskScoreResource($score))->resolve() : null,
            ];
        });

        // Distribución por nivel para el resumen superior
        $summary = [
            'critico' => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'critico')->count(),
            'alto'    => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'alto')->count(),
            'medio'   => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'medio')->count(),
            'bajo'    => $contracts->filter(fn ($c) => $c->latestRiskScore?->score_level === 'bajo')->count(),
            'sin_score' => $contracts->filter(fn ($c) => !$c->latestRiskScore)->count(),
        ];

        return Inertia::render('Risk/Index', [
            'contracts' => $contractsData,
            'summary'   => $summary,
        ]);
    }

    public function show(Contract $contract): Response
    {
        $contract->load(['mandante', 'contractor']);

        $latestScore = $contract->latestRiskScore;
        $history = ClaimRiskScore::where('contract_id', $contract->id)
            ->orderByDesc('calculated_at')
            ->take(30)
            ->get();

        return Inertia::render('Risk/Show', [
            'contract'    => [
                'id'     => $contract->id,
                'name'   => $contract->name,
                'number' => $contract->number,
                'status' => $contract->status,
                'status_label' => Contract::STATUS_LABELS[$contract->status] ?? $contract->status,
                'mandante'   => $contract->mandante?->name,
                'contractor' => $contract->contractor?->name,
            ],
            'latest_score' => $latestScore ? (new ClaimRiskScoreResource($latestScore))->resolve() : null,
            'history'      => ClaimRiskScoreResource::collection($history)->resolve(),
        ]);
    }

    /**
     * Recálculo manual desde la UI (botón "Recalcular ahora").
     */
    public function recalculate(Contract $contract): RedirectResponse
    {
        $this->service->calculate($contract);

        return back()->with('success', 'Score de riesgo recalculado correctamente.');
    }
}
