<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContractPdfRequest;
use App\Http\Requests\ContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ContractPriceItem;
use App\Models\ContractUser;
use App\Models\User;
use App\Services\ContractPdfService;
use App\Services\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Multitenancy\Models\Tenant;

class ContractController extends Controller
{
    public function __construct(private readonly ContractService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);

        return Inertia::render('Contracts/Index', [
            'contracts' => ContractResource::collection(
                $this->service->paginate($request->only('search', 'status', 'type'))
            ),
            'filters'   => $request->only('search', 'status', 'type'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Contract::class);

        return Inertia::render('Contracts/Form', [
            'companies' => $this->service->companiesForSelect(),
        ]);
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $contract = $this->service->create($request->validated(), $request->user());

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', "Contrato {$contract->number} creado correctamente.");
    }

    public function show(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load(['mandante', 'contractor', 'assignedUsers.user', 'documents' => fn ($q) => $q->constitutive()->orderBy('precedence_order')]);

        $tenantId        = Tenant::current()?->id;
        $assignedUserIds = $contract->assignedUsers->pluck('user_id')->toArray();

        $availableUsers = User::where('tenant_id', $tenantId)
            ->whereNotIn('id', $assignedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Contracts/Show', [
            'contract'       => ContractResource::make($contract)->resolve(),
            'assignedUsers'  => $contract->assignedUsers->map(fn ($cu) => [
                'id'         => $cu->id,
                'user_id'    => $cu->user_id,
                'name'       => $cu->user->name,
                'email'      => $cu->user->email,
                'role'       => $cu->role,
                'role_label' => ContractUser::ROLE_LABELS[$cu->role] ?? $cu->role,
            ]),
            'availableUsers' => $availableUsers->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
            ]),
            'roleOptions'         => collect(ContractUser::ROLE_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'corpusDocs'          => $contract->documents->map(fn ($d) => [
                'id'             => $d->id,
                'name'           => $d->name,
                'category'       => $d->category,
                'category_label' => ContractDocument::CONSTITUTIVE_LABELS[$d->category] ?? $d->category,
                'has_text'       => !empty($d->extracted_text),
                'precedence_order' => $d->precedence_order,
                'file_size_human'  => $d->file_size_human,
            ]),
            'corpusCategories'    => collect(ContractDocument::CONSTITUTIVE_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'priceItems'          => $contract->priceItems()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('code')
                ->get(['id', 'code', 'description', 'unit', 'unit_cost', 'category'])
                ->map(fn ($p) => [
                    'id'          => $p->id,
                    'code'        => $p->code,
                    'description' => $p->description,
                    'unit'        => $p->unit,
                    'unit_cost'   => $p->unit_cost,
                    'category'    => $p->category,
                ]),
            'categoryLabels'      => ContractPriceItem::CATEGORY_LABELS,
        ]);
    }

    public function edit(Contract $contract): Response
    {
        $this->authorize('update', $contract);

        $contract->load('mandante', 'contractor');

        return Inertia::render('Contracts/Form', [
            'contract'  => ContractResource::make($contract)->resolve(),
            'companies' => $this->service->companiesForSelect(),
        ]);
    }

    public function update(ContractRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $this->service->update($contract, $request->validated());

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', 'Contrato actualizado correctamente.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        $this->service->delete($contract);

        return redirect()->route('contracts.index')
            ->with('success', 'Contrato eliminado.');
    }

    public function changeStatus(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $request->validate([
            'status' => ['required', 'in:borrador,vigente,suspendido,terminado,en_disputa'],
        ]);

        $this->service->changeStatus($contract, $request->status);

        return back()->with('success', 'Estado del contrato actualizado.');
    }

    public function uploadPdf(ContractPdfRequest $request, Contract $contract, ContractPdfService $pdfService): RedirectResponse
    {
        $this->authorize('update', $contract);

        $text = $pdfService->extractText($request->file('pdf'));

        $contract->update([
            'contract_text'    => $text,
            'contract_pdf_name' => $request->file('pdf')->getClientOriginalName(),
        ]);

        return back()->with('success', 'PDF del contrato cargado correctamente. La IA usará este documento como contexto.');
    }

    public function removePdf(Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $contract->update([
            'contract_text'    => null,
            'contract_pdf_name' => null,
        ]);

        return back()->with('success', 'Documento base eliminado.');
    }

    public function uploadCorpusDoc(Request $request, Contract $contract, ContractPdfService $pdfService): RedirectResponse
    {
        $this->authorize('update', $contract);

        $request->validate([
            'pdf'              => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'category'         => ['required', 'string', 'in:' . implode(',', array_keys(ContractDocument::CONSTITUTIVE_LABELS))],
            'name'             => ['nullable', 'string', 'max:200'],
            'precedence_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $file = $request->file('pdf');
        $text = $pdfService->extractText($file);

        $contract->documents()->create([
            'name'             => $request->input('name') ?: $file->getClientOriginalName(),
            'category'         => $request->input('category'),
            'is_constitutive'  => true,
            'extracted_text'   => $text,
            'precedence_order' => $request->input('precedence_order', 0),
            'file_type'        => $file->getClientMimeType(),
            'file_size'        => $file->getSize(),
            'uploaded_by'      => $request->user()->id,
        ]);

        return back()->with('success', 'Documento contractual cargado y procesado correctamente.');
    }

    public function removeCorpusDoc(Contract $contract, ContractDocument $document): RedirectResponse
    {
        $this->authorize('update', $contract);

        $document->delete();

        return back()->with('success', 'Documento eliminado del cuerpo contractual.');
    }

    public function assignUser(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $request->validate([
            'user_id' => ['required', 'integer'],
            'role'    => ['required', 'string', 'in:' . implode(',', array_keys(ContractUser::ROLE_LABELS))],
        ]);

        $tenantId = Tenant::current()?->id;
        $user = User::where('id', $request->user_id)->where('tenant_id', $tenantId)->first();

        if (!$user) {
            return back()->withErrors(['user_id' => 'Usuario inválido.']);
        }

        $alreadyAssigned = $contract->assignedUsers()->where('user_id', $request->user_id)->exists();
        if ($alreadyAssigned) {
            return back()->with('error', 'Este usuario ya está asignado al contrato.');
        }

        $contract->assignedUsers()->create([
            'user_id' => $request->user_id,
            'role'    => $request->role,
        ]);

        return back()->with('success', "Usuario {$user->name} asignado al contrato.");
    }

    public function removeUser(Contract $contract, ContractUser $contractUser): RedirectResponse
    {
        $this->authorize('update', $contract);

        $contractUser->delete();

        return back()->with('success', 'Usuario removido del contrato.');
    }
}
