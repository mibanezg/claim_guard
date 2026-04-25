<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractualEvent;
use App\Models\EventDelayAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DelayAnalysisController extends Controller
{
    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])->orderBy('name')->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $events = [];
        if ($selectedContract) {
            $events = $selectedContract->events()
                ->where('schedule_impact_days', '>', 0)
                ->with('delayAnalysis')
                ->orderByDesc('occurred_at')
                ->get()
                ->map(fn ($e) => [
                    'id'                   => $e->id,
                    'type_label'           => $e->type_label,
                    'occurred_at'          => $e->occurred_at?->format('d/m/Y'),
                    'description'          => $e->description,
                    'schedule_impact_days' => $e->schedule_impact_days,
                    'party_label'          => $e->party_label,
                    'responsible_party'    => $e->responsible_party,
                    'has_analysis'         => !is_null($e->delayAnalysis),
                    'delay_type_label'     => $e->delayAnalysis?->delay_type_label,
                    'is_critical_path'     => $e->delayAnalysis?->is_critical_path,
                    'analysis_method_label'=> $e->delayAnalysis?->method_label,
                ])->toArray();
        }

        return Inertia::render('DelayAnalysis/Index', [
            'contracts'        => ContractResource::collection($contracts),
            'selectedContract' => $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'events'           => $events,
            'flash'            => session()->only(['success', 'error']),
        ]);
    }

    public function show(Contract $contract, ContractualEvent $event): Response
    {
        $this->authorize('view', $contract);

        $analysis   = $event->delayAnalysis;
        $milestones = $contract->milestones()->orderBy('planned_date')->get();

        return Inertia::render('DelayAnalysis/Show', [
            'contract'   => ContractResource::make($contract->load(['mandante', 'contractor']))->resolve(),
            'event'      => [
                'id'                   => $event->id,
                'type_label'           => $event->type_label,
                'occurred_at'          => $event->occurred_at?->format('d/m/Y'),
                'occurred_at_raw'      => $event->occurred_at?->toDateString(),
                'description'          => $event->description,
                'schedule_impact_days' => $event->schedule_impact_days,
                'party_label'          => $event->party_label,
                'responsible_party'    => $event->responsible_party,
            ],
            'analysis'   => $analysis ? [
                'id'               => $analysis->id,
                'affected_milestone_id' => $analysis->affected_milestone_id,
                'delay_type'       => $analysis->delay_type,
                'is_critical_path' => $analysis->is_critical_path,
                'analysis_method'  => $analysis->analysis_method,
                'baseline_date'    => $analysis->baseline_date?->toDateString(),
                'impacted_date'    => $analysis->impacted_date?->toDateString(),
                'delay_days'       => $analysis->delay_days,
                'float_consumed'   => $analysis->float_consumed,
                'concurrent_cause' => $analysis->concurrent_cause,
                'narrative'        => $analysis->narrative,
            ] : null,
            'milestones' => $milestones->map(fn ($m) => [
                'id'          => $m->id,
                'name'        => $m->name,
                'planned_date'=> $m->planned_date?->format('d/m/Y'),
                'is_critical' => $m->is_critical,
            ])->toArray(),
            'delayTypeLabels'  => EventDelayAnalysis::DELAY_TYPE_LABELS,
            'methodLabels'     => EventDelayAnalysis::METHOD_LABELS,
            'flash'            => session()->only(['success', 'error']),
        ]);
    }

    public function save(Request $request, Contract $contract, ContractualEvent $event): RedirectResponse
    {
        $this->authorize('update', $contract);

        $data = $request->validate([
            'affected_milestone_id' => ['nullable', 'integer'],
            'delay_type'            => ['required', Rule::in(array_keys(EventDelayAnalysis::DELAY_TYPE_LABELS))],
            'is_critical_path'      => ['boolean'],
            'analysis_method'       => ['required', Rule::in(array_keys(EventDelayAnalysis::METHOD_LABELS))],
            'baseline_date'         => ['nullable', 'date'],
            'impacted_date'         => ['nullable', 'date', 'after_or_equal:baseline_date'],
            'float_consumed'        => ['nullable', 'integer', 'min:0'],
            'concurrent_cause'      => ['nullable', 'string', 'max:500'],
            'narrative'             => ['required', 'string', 'min:20'],
        ]);

        $data['contractual_event_id'] = $event->id;

        EventDelayAnalysis::updateOrCreate(
            ['contractual_event_id' => $event->id],
            $data
        );

        return redirect()
            ->route('delay-analysis.index', ['contract_id' => $contract->id])
            ->with('success', 'Análisis de plazo guardado correctamente.');
    }
}
