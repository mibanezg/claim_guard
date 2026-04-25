<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyReportRequest;
use App\Http\Resources\ContractResource;
use App\Http\Resources\DailyReportResource;
use App\Models\Contract;
use App\Models\DailyReport;
use App\Services\DailyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportController extends Controller
{
    public function __construct(private DailyReportService $service) {}

    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])
            ->orderBy('name')->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $filters = $request->only('month');

        $reports     = null;
        $missingDays = [];

        if ($selectedContract) {
            $reports = DailyReportResource::collection(
                $this->service->paginate($selectedContract, $filters)
            );
            $missingDays = $this->service->missingDays($selectedContract);
        }

        return Inertia::render('DailyReports/Index', [
            'contracts'       => ContractResource::collection($contracts),
            'selectedContract'=> $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'reports'         => $reports,
            'filters'         => $filters,
            'missingDays'     => $missingDays,
            'flash'           => session()->only(['success', 'error']),
            'weatherLabels'   => \App\Models\DailyReport::WEATHER_LABELS,
            'weatherIcons'    => \App\Models\DailyReport::WEATHER_ICONS,
        ]);
    }

    public function create(Request $request): Response
    {
        $contract = Contract::with(['mandante', 'contractor', 'events' => fn ($q) => $q->orderByDesc('occurred_at')])->findOrFail($request->input('contract_id'));
        $this->authorize('update', $contract);

        return Inertia::render('DailyReports/Form', [
            'contract'     => ContractResource::make($contract)->resolve(),
            'report'       => null,
            'weatherLabels'=> \App\Models\DailyReport::WEATHER_LABELS,
            'contractEvents'=> $contract->events->map(fn ($e) => [
                'id'         => $e->id,
                'type_label' => $e->type_label,
                'occurred_at'=> $e->occurred_at?->format('d/m/Y'),
                'description'=> $e->description,
            ]),
        ]);
    }

    public function store(DailyReportRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->service->create($contract, $request->validated(), Auth::id());

        return redirect()
            ->route('daily-reports.index', ['contract_id' => $contract->id])
            ->with('success', 'Reporte diario registrado correctamente.');
    }

    public function edit(Contract $contract, DailyReport $dailyReport): Response
    {
        $this->authorize('update', $contract);
        $dailyReport->load('events');

        $contractEvents = $contract->events()->orderByDesc('occurred_at')->get()->map(fn ($e) => [
            'id'         => $e->id,
            'type_label' => $e->type_label,
            'occurred_at'=> $e->occurred_at?->format('d/m/Y'),
            'description'=> $e->description,
        ]);

        return Inertia::render('DailyReports/Form', [
            'contract'      => ContractResource::make($contract->load(['mandante', 'contractor']))->resolve(),
            'report'        => DailyReportResource::make($dailyReport)->resolve(),
            'weatherLabels' => \App\Models\DailyReport::WEATHER_LABELS,
            'contractEvents'=> $contractEvents,
        ]);
    }

    public function update(DailyReportRequest $request, Contract $contract, DailyReport $dailyReport): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->service->update($dailyReport, $request->validated());

        return redirect()
            ->route('daily-reports.index', ['contract_id' => $contract->id])
            ->with('success', 'Reporte actualizado correctamente.');
    }

    public function destroy(Contract $contract, DailyReport $dailyReport): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->service->delete($dailyReport);

        return redirect()
            ->route('daily-reports.index', ['contract_id' => $contract->id])
            ->with('success', 'Reporte eliminado.');
    }
}
