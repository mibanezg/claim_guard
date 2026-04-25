<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Http\Resources\ContractResource;
use App\Http\Resources\EventResource;
use App\Models\Contract;
use App\Models\ContractualEvent;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private EventService $service) {}

    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])
            ->orderBy('name')->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $filters = $request->only('type', 'responsible_party', 'resolution_status');

        $events = $selectedContract
            ? EventResource::collection(
                $this->service->paginate($selectedContract, $filters)
                    ->through(fn ($e) => $e->loadCount(['letters', 'changeOrders', 'rightsLetter']))
              )
            : null;

        return Inertia::render('Events/Index', [
            'contracts'           => ContractResource::collection($contracts),
            'selectedContract'    => $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'events'              => $events,
            'filters'             => $filters,
            'flash'               => session()->only(['success', 'error']),
            'typeLabels'          => \App\Models\ContractualEvent::TYPE_LABELS,
            'partyLabels'         => \App\Models\ContractualEvent::PARTY_LABELS,
            'resolutionLabels'    => \App\Models\ContractualEvent::RESOLUTION_LABELS,
            'notificationLabels'  => \App\Models\ContractualEvent::NOTIFICATION_LABELS,
            'basisDocLabels'      => \App\Models\ContractualEvent::BASIS_DOC_LABELS,
        ]);
    }

    public function store(EventRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('create', ContractualEvent::class);
        $this->service->create($contract, $request->validated(), Auth::id());

        return redirect()
            ->route('events.index', ['contract_id' => $contract->id])
            ->with('success', 'Evento registrado correctamente.');
    }

    public function update(EventRequest $request, Contract $contract, ContractualEvent $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $this->service->update($event, $request->validated());

        return redirect()
            ->route('events.index', ['contract_id' => $contract->id])
            ->with('success', 'Evento actualizado correctamente.');
    }

    public function destroy(Contract $contract, ContractualEvent $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $this->service->delete($event);

        return redirect()
            ->route('events.index', ['contract_id' => $contract->id])
            ->with('success', 'Evento eliminado.');
    }
}
