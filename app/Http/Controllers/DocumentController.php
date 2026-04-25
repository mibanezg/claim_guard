<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractDocumentResource;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Services\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(private DocumentStorageService $storage) {}

    public function index(Request $request): Response
    {
        $contractId = $request->integer('contract_id');

        $contracts = Contract::with(['mandante', 'contractor'])
            ->whereIn('status', ['vigente', 'suspendido', 'en_disputa', 'terminado'])
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'number', 'status']);

        $selectedContract = $contractId
            ? Contract::find($contractId)
            : null;

        $documents = $selectedContract
            ? ContractDocumentResource::collection(
                $this->storage->listByContract($selectedContract, $request->only('category'))
              )
            : null;

        return Inertia::render('Documents/Index', [
            'contracts'           => $contracts,
            'selectedContract'    => $selectedContract,
            'documents'           => $documents,
            'filters'             => $request->only('category'),
            'categoryLabels'      => ContractDocument::CATEGORY_LABELS,
            'sharepoint_active'   => $this->storage->isSharePointConfigured(),
        ]);
    }

    public function store(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('create', ContractDocument::class);

        $request->validate([
            'file'     => ['required', 'file', 'max:51200'], // 50 MB
            'category' => ['required', 'string'],
            'event_id'        => ['nullable', 'integer'],
            'letter_id'       => ['nullable', 'integer'],
            'change_order_id' => ['nullable', 'integer'],
        ]);

        $this->storage->upload(
            $request->file('file'),
            $contract,
            $request->input('category'),
            $request->user()->id,
            $request->only('event_id', 'letter_id', 'change_order_id'),
        );

        return back()->with('success', 'Documento subido correctamente.');
    }

    public function destroy(Contract $contract, ContractDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);
        abort_if($document->contract_id !== $contract->id, 403);

        $this->storage->delete($document);

        return back()->with('success', 'Documento eliminado.');
    }
}
