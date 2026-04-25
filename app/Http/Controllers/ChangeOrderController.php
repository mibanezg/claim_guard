<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeOrderRequest;
use App\Http\Resources\ChangeOrderResource;
use App\Http\Resources\ContractResource;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Services\ChangeOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChangeOrderController extends Controller
{
    public function __construct(private ChangeOrderService $service) {}

    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])
            ->orderBy('name')->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $filters = $request->only('status', 'requested_by_party');

        $changeOrders = $selectedContract
            ? ChangeOrderResource::collection($this->service->paginate($selectedContract, $filters))
            : null;

        $contractEvents = $selectedContract
            ? $selectedContract->events()->orderByDesc('occurred_at')->get(['id', 'type', 'occurred_at', 'description'])
            : collect();

        return Inertia::render('ChangeOrders/Index', [
            'contracts'        => ContractResource::collection($contracts),
            'selectedContract' => $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'changeOrders'     => $changeOrders,
            'contractEvents'   => $contractEvents->map(fn ($e) => [
                'id'          => $e->id,
                'label'       => $e->occurred_at->format('d/m/Y') . ' — ' . (\App\Models\ContractualEvent::TYPE_LABELS[$e->type] ?? $e->type),
                'description' => \Str::limit($e->description, 60),
            ]),
            'filters'          => $filters,
            'flash'            => session()->only(['success', 'error']),
            'statusLabels'     => ChangeOrder::STATUS_LABELS,
            'partyLabels'      => ChangeOrder::PARTY_LABELS,
        ]);
    }

    public function store(ChangeOrderRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('create', ChangeOrder::class);
        $this->service->create($contract, $request->validated(), Auth::id());

        return redirect()
            ->route('change-orders.index', ['contract_id' => $contract->id])
            ->with('success', 'Orden de cambio registrada correctamente.');
    }

    public function update(ChangeOrderRequest $request, Contract $contract, ChangeOrder $changeOrder): RedirectResponse
    {
        $this->authorize('update', $changeOrder);

        $this->service->update($changeOrder, $request->validated());

        return redirect()
            ->route('change-orders.index', ['contract_id' => $contract->id])
            ->with('success', 'Orden de cambio actualizada correctamente.');
    }

    public function destroy(Contract $contract, ChangeOrder $changeOrder): RedirectResponse
    {
        $this->authorize('delete', $changeOrder);

        $this->service->delete($changeOrder);

        return redirect()
            ->route('change-orders.index', ['contract_id' => $contract->id])
            ->with('success', 'Orden de cambio eliminada.');
    }
}
