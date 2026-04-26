<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Jobs\AnalyzeClaimExposureJob;
use App\Models\Contract;
use App\Models\ContractAiAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Multitenancy\Models\Tenant;

class ClaimAnalysisController extends Controller
{
    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])
            ->orderBy('name')->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $analysis    = null;
        $isProcessing = false;

        if ($selectedContract) {
            $analysis = ContractAiAnalysis::where('contract_id', $selectedContract->id)
                ->latest()
                ->first();
            $isProcessing = $analysis?->isProcessing() ?? false;
        }

        return Inertia::render('Analysis/Index', [
            'contracts'        => ContractResource::collection($contracts),
            'selectedContract' => $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'analysis'         => $analysis ? $this->formatAnalysis($analysis) : null,
            'isProcessing'     => $isProcessing,
            'flash'            => session()->only(['success', 'error']),
        ]);
    }

    public function generate(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        // Evitar análisis duplicados mientras está procesando
        $existing = ContractAiAnalysis::where('contract_id', $contract->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($existing) {
            return redirect()
                ->route('analysis.index', ['contract_id' => $contract->id])
                ->with('error', 'Ya hay un análisis en proceso para este contrato.');
        }

        try {
            $analysis = ContractAiAnalysis::create([
                'contract_id'  => $contract->id,
                'status'       => 'pending',
                'requested_by' => Auth::id(),
            ]);

            $tenant = Tenant::current();
            if ($tenant) {
                AnalyzeClaimExposureJob::dispatch($analysis->id, $tenant->id);
            }

            return redirect()
                ->route('analysis.index', ['contract_id' => $contract->id])
                ->with('success', 'Análisis iniciado. La IA está procesando el contrato, esto puede tomar un minuto.');

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ClaimAnalysisController::generate error', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('analysis.index', ['contract_id' => $contract->id])
                ->with('error', 'Error al iniciar el análisis: ' . $e->getMessage());
        }
    }

    public function status(Contract $contract): JsonResponse
    {
        $analysis = ContractAiAnalysis::where('contract_id', $contract->id)->latest()->first();
        return response()->json([
            'isProcessing' => $analysis?->isProcessing() ?? false,
        ]);
    }

    private function formatAnalysis(ContractAiAnalysis $analysis): array
    {
        return [
            'id'                    => $analysis->id,
            'status'                => $analysis->status,
            'is_processing'         => $analysis->isProcessing(),
            'exposure_assessment'   => $analysis->exposure_assessment,
            'strong_points'         => $analysis->strong_points ?? [],
            'weak_points'           => $analysis->weak_points ?? [],
            'urgent_actions'        => $analysis->urgent_actions ?? [],
            'pattern_observations'  => $analysis->pattern_observations ?? [],
            'key_clauses'           => $analysis->key_clauses ?? [],
            'estimated_exposure_days'=> $analysis->estimated_exposure_days,
            'estimated_exposure_cost'=> $analysis->estimated_exposure_cost
                ? $analysis->estimated_exposure_cost / 100
                : null,
            'analysis_confidence'   => $analysis->analysis_confidence,
            'confidence_note'       => $analysis->confidence_note,
            'error_message'         => $analysis->error_message,
            'completed_at'          => $analysis->completed_at?->format('d/m/Y H:i'),
            'created_at'            => $analysis->created_at?->format('d/m/Y H:i'),
        ];
    }
}
