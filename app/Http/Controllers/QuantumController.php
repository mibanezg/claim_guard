<?php

namespace App\Http\Controllers;

use App\Exports\QuantumContractExport;
use App\Exports\QuantumEventExport;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractualEvent;
use App\Models\EventCostItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuantumController extends Controller
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
                ->withCount('costItems')
                ->withSum('costItems as quantum_sum', 'amount')
                ->orderByDesc('occurred_at')
                ->get()
                ->map(fn ($e) => [
                    'id'                   => $e->id,
                    'type_label'           => $e->type_label,
                    'occurred_at'          => $e->occurred_at?->format('d/m/Y'),
                    'description'          => $e->description,
                    'cost_impact'          => $e->cost_impact / 100,
                    'cost_impact_raw'      => $e->cost_impact,
                    'cost_items_count'     => $e->cost_items_count,
                    'quantum_total'        => ($e->quantum_sum ?? 0) / 100,
                    'quantum_total_raw'    => (int) ($e->quantum_sum ?? 0),
                    'has_quantum'          => $e->cost_items_count > 0,
                    'reconciled'           => $e->cost_items_count > 0 &&
                                             abs(($e->quantum_sum ?? 0) - $e->cost_impact) < 100,
                ])->toArray();
        }

        return Inertia::render('Quantum/Index', [
            'contracts'        => ContractResource::collection($contracts),
            'selectedContract' => $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'events'           => $events,
            'flash'            => session()->only(['success', 'error']),
        ]);
    }

    public function show(Contract $contract, ContractualEvent $event): Response
    {
        $this->authorize('view', $contract);

        $costItems = $event->costItems()->with('priceItem')->orderBy('cost_category')->get();
        $priceItems = $contract->priceItems()->where('is_active', true)->orderBy('description')->get();

        // Totales por categoría
        $totals = $costItems->groupBy('cost_category')->map(fn ($items) => [
            'label'  => EventCostItem::CATEGORY_LABELS[$items->first()->cost_category] ?? '',
            'amount' => $items->sum('amount') / 100,
        ]);

        $directTotal   = $costItems->whereIn('cost_category', EventCostItem::DIRECT_CATEGORIES)->sum('amount');
        $indirectTotal = $costItems->whereIn('cost_category', EventCostItem::INDIRECT_CATEGORIES)->sum('amount');
        $profitTotal   = $costItems->where('cost_category', 'profit')->sum('amount');
        $grandTotal    = $costItems->sum('amount');

        return Inertia::render('Quantum/Show', [
            'contract'    => ContractResource::make($contract->load(['mandante', 'contractor']))->resolve(),
            'event'       => [
                'id'           => $event->id,
                'type_label'   => $event->type_label,
                'occurred_at'  => $event->occurred_at?->format('d/m/Y'),
                'description'  => $event->description,
                'cost_impact'  => $event->cost_impact / 100,
                'party_label'  => $event->party_label,
            ],
            'costItems'   => $costItems->map(fn ($item) => [
                'id'               => $item->id,
                'price_item_id'    => $item->contract_price_item_id,
                'description'      => $item->description,
                'unit'             => $item->unit,
                'quantity'         => $item->quantity,
                'unit_cost'        => $item->unit_cost / 100,
                'amount'           => $item->amount / 100,
                'cost_category'    => $item->cost_category,
                'cost_category_label' => EventCostItem::CATEGORY_LABELS[$item->cost_category] ?? '',
                'notes'            => $item->notes,
                'is_from_catalog'  => $item->is_from_catalog,
            ])->toArray(),
            'priceItems'  => $priceItems->map(fn ($p) => [
                'id'          => $p->id,
                'code'        => $p->code,
                'description' => $p->description,
                'unit'        => $p->unit,
                'unit_cost'   => $p->unit_cost / 100,
                'category'    => $p->category,
                'label'       => $p->display_label,
            ])->toArray(),
            'totals'       => $totals->toArray(),
            'directTotal'  => $directTotal / 100,
            'indirectTotal'=> $indirectTotal / 100,
            'profitTotal'  => $profitTotal / 100,
            'grandTotal'   => $grandTotal / 100,
            'categoryLabels' => EventCostItem::CATEGORY_LABELS,
            'flash'        => session()->only(['success', 'error']),
        ]);
    }

    public function storeCostItem(Request $request, Contract $contract, ContractualEvent $event): RedirectResponse
    {
        $this->authorize('update', $contract);

        $data = $request->validate([
            'contract_price_item_id' => ['nullable', 'integer'],
            'description'            => ['required', 'string', 'max:255'],
            'unit'                   => ['required', 'string', 'max:30'],
            'quantity'               => ['required', 'numeric', 'min:0.001'],
            'unit_cost'              => ['required', 'numeric', 'min:0.01'],
            'cost_category'          => ['required', Rule::in(array_keys(EventCostItem::CATEGORY_LABELS))],
            'notes'                  => ['nullable', 'string'],
        ]);

        $unitCost = (int) round($data['unit_cost'] * 100);
        $amount   = (int) round($data['quantity'] * $unitCost);

        EventCostItem::create([
            'contractual_event_id'   => $event->id,
            'contract_price_item_id' => $data['contract_price_item_id'] ?? null,
            'description'            => $data['description'],
            'unit'                   => $data['unit'],
            'quantity'               => $data['quantity'],
            'unit_cost'              => $unitCost,
            'amount'                 => $amount,
            'cost_category'          => $data['cost_category'],
            'notes'                  => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Ítem agregado al quantum.');
    }

    public function exportEvent(Contract $contract, ContractualEvent $event): BinaryFileResponse
    {
        $this->authorize('view', $contract);

        $event->load(['contract.mandante', 'contract.contractor', 'costItems']);

        $filename = "quantum_{$contract->number}_{$event->occurred_at?->format('Ymd')}.xlsx";

        return Excel::download(new QuantumEventExport($event, $contract->currency), $filename);
    }

    public function exportContract(Contract $contract): BinaryFileResponse
    {
        $this->authorize('view', $contract);

        $filename = "quantum_{$contract->number}_completo.xlsx";

        return Excel::download(new QuantumContractExport($contract->load(['mandante', 'contractor'])), $filename);
    }

    public function destroyCostItem(Contract $contract, ContractualEvent $event, EventCostItem $costItem): RedirectResponse
    {
        $this->authorize('update', $contract);
        $costItem->delete();

        return back()->with('success', 'Ítem eliminado.');
    }
}
