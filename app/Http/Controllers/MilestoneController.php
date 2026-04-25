<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilestoneRequest;
use App\Http\Resources\ContractResource;
use App\Http\Resources\MilestoneResource;
use App\Jobs\ImportMicrosoftProjectJob;
use App\Jobs\ImportPrimaveraJob;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Services\MilestoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MilestoneController extends Controller
{
    public function __construct(private MilestoneService $service) {}

    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])
            ->orderBy('name')
            ->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $milestones = $selectedContract
            ? MilestoneResource::collection(
                $selectedContract->milestones()
                    ->orderBy('planned_date')
                    ->get()
              )
            : collect();

        return Inertia::render('Milestones/Index', [
            'contracts'        => ContractResource::collection($contracts),
            'selectedContract' => $selectedContract ? new ContractResource($selectedContract) : null,
            'milestones'       => $milestones,
            'flash'            => session()->only(['success', 'error', 'info']),
        ]);
    }

    public function store(MilestoneRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->service->create($contract, $request->validated());

        return redirect()
            ->route('milestones.index', ['contract_id' => $contract->id])
            ->with('success', 'Hito creado correctamente.');
    }

    public function update(MilestoneRequest $request, Contract $contract, ContractMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->service->update($milestone, $request->validated());

        return redirect()
            ->route('milestones.index', ['contract_id' => $contract->id])
            ->with('success', 'Hito actualizado correctamente.');
    }

    public function destroy(Contract $contract, ContractMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->service->delete($milestone);

        return redirect()
            ->route('milestones.index', ['contract_id' => $contract->id])
            ->with('success', 'Hito eliminado correctamente.');
    }

    public function import(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            $count = $this->service->importFromExcel($contract, $request->file('file'));
            return redirect()
                ->route('milestones.index', ['contract_id' => $contract->id])
                ->with('success', "Importación completada. {$count} hito(s) nuevo(s) agregado(s).");
        } catch (\Exception $e) {
            return back()->with('error', 'Error al importar el archivo. Verifica el formato.');
        }
    }

    public function importPrimavera(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xer,xml,txt', 'max:20480'],
        ], [
            'file.mimes' => 'El archivo debe ser XER o XML exportado desde Primavera P6.',
        ]);

        $path = $request->file('file')->store("imports/primavera/{$contract->id}");

        ImportPrimaveraJob::dispatch($contract, $path, Auth::id());

        return redirect()
            ->route('milestones.index', ['contract_id' => $contract->id])
            ->with('info', 'El archivo de Primavera P6 está siendo procesado. Los hitos aparecerán en unos momentos.');
    }

    public function importMsProject(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xml', 'max:20480'],
        ], [
            'file.mimes' => 'El archivo debe ser un XML exportado desde Microsoft Project.',
        ]);

        // Guarda temporalmente en storage para procesamiento asíncrono
        $path = $request->file('file')->store("imports/ms_project/{$contract->id}");

        ImportMicrosoftProjectJob::dispatch($contract, $path, Auth::id());

        return redirect()
            ->route('milestones.index', ['contract_id' => $contract->id])
            ->with('info', 'El archivo XML está siendo procesado. Los hitos aparecerán en unos momentos.');
    }
}
