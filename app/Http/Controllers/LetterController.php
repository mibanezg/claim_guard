<?php

namespace App\Http\Controllers;

use App\Http\Requests\LetterRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ContractResource;
use App\Http\Resources\LetterResource;
use App\Jobs\GenerateLetterDraftJob;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLetter;
use App\Services\AiService;
use App\Services\LetterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Multitenancy\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class LetterController extends Controller
{
    public function __construct(
        private LetterService $service,
        private AiService $ai,
    ) {}

    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])
            ->orderBy('name')->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $filters = $request->only('type', 'status');

        $letters = $selectedContract
            ? LetterResource::collection($this->service->paginate($selectedContract, $filters))
            : null;

        $companies = $selectedContract
            ? Company::whereIn('id', array_filter([
                $selectedContract->mandante_company_id,
                $selectedContract->contractor_company_id,
            ]))->orderBy('name')->get()
            : collect();
        $contractEvents = $selectedContract
            ? $selectedContract->events()->orderByDesc('occurred_at')->get(['id', 'type', 'occurred_at', 'description'])
            : collect();

        return Inertia::render('Letters/Index', [
            'contracts'           => ContractResource::collection($contracts),
            'selectedContract'    => $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'letters'             => $letters,
            'companies'           => CompanyResource::collection($companies),
            'contractEvents'      => $contractEvents->map(fn ($e) => [
                'id'          => $e->id,
                'label'       => $e->occurred_at->format('d/m/Y') . ' — ' . (\App\Models\ContractualEvent::TYPE_LABELS[$e->type] ?? $e->type),
                'description' => \Str::limit($e->description, 60),
            ]),
            'filters'             => $filters,
            'flash'               => session()->only(['success', 'error']),
            'typeLabels'          => ContractLetter::TYPE_LABELS,
            'statusLabels'        => ContractLetter::STATUS_LABELS,
            'defaultResponseDays' => ContractLetter::DEFAULT_RESPONSE_DAYS,
            'ai_available'        => $this->ai->isConfigured(),
        ]);
    }

    public function store(LetterRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('create', ContractLetter::class);
        $this->service->create($contract, $request->validated(), Auth::id());

        return redirect()
            ->route('letters.index', ['contract_id' => $contract->id])
            ->with('success', 'Carta registrada correctamente.');
    }

    public function update(LetterRequest $request, Contract $contract, ContractLetter $letter): RedirectResponse
    {
        $this->authorize('update', $letter);
        $this->service->update($letter, $request->validated());

        return redirect()
            ->route('letters.index', ['contract_id' => $contract->id])
            ->with('success', 'Carta actualizada correctamente.');
    }

    /**
     * Solicita a la IA generar un borrador para una carta existente.
     */
    public function requestDraft(Request $request, Contract $contract, ContractLetter $letter): RedirectResponse
    {
        $this->authorize('update', $letter);

        $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        if (!$this->ai->isConfigured()) {
            return back()->with('error', 'La generación con IA no está disponible. Configura la API key de Anthropic en el servidor.');
        }

        $tenant = Tenant::current();
        GenerateLetterDraftJob::dispatch($letter->id, $tenant->id, $request->input('description'), Auth::id());

        return back()->with('success', 'Solicitud enviada. El borrador estará listo en unos segundos.');
    }

    public function destroy(Contract $contract, ContractLetter $letter): RedirectResponse
    {
        $this->authorize('delete', $letter);
        $this->service->delete($letter);

        return redirect()
            ->route('letters.index', ['contract_id' => $contract->id])
            ->with('success', 'Carta eliminada.');
    }
}
