<?php

namespace App\Http\Controllers;

use App\Exports\ChangeOrdersExport;
use App\Exports\ContractsExport;
use App\Exports\CurvaSExport;
use App\Exports\EventsExport;
use App\Exports\LettersExport;
use App\Models\Contract;
use App\Models\ContractLetter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $contracts = Contract::orderBy('number')
            ->whereIn('status', ['vigente', 'suspendido', 'en_disputa', 'terminado'])
            ->get(['id', 'name', 'number', 'status']);

        return Inertia::render('Reports/Index', [
            'contracts' => $contracts->map(fn ($c) => [
                'id'     => $c->id,
                'label'  => $c->number . ' — ' . $c->name,
                'status' => $c->status,
            ]),
        ]);
    }

    // ── Excel exports ─────────────────────────────────────────────────────────

    public function exportContractsExcel(): BinaryFileResponse
    {
        $filename = 'contratos-' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new ContractsExport, $filename);
    }

    public function exportEventsExcel(Request $request): BinaryFileResponse
    {
        $contractId = $request->integer('contract_id') ?: null;
        $filename   = 'eventos-' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new EventsExport($contractId), $filename);
    }

    public function exportLettersExcel(Request $request): BinaryFileResponse
    {
        $contractId = $request->integer('contract_id') ?: null;
        $filename   = 'correspondencia-' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new LettersExport($contractId), $filename);
    }

    public function exportChangeOrdersExcel(Request $request): BinaryFileResponse
    {
        $contractId = $request->integer('contract_id') ?: null;
        $filename   = 'ordenes-cambio-' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new ChangeOrdersExport($contractId), $filename);
    }

    public function exportCurvaSExcel(Request $request): BinaryFileResponse
    {
        $contractId = $request->integer('contract_id');
        $filename   = 'curva-s-' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new CurvaSExport($contractId), $filename);
    }

    // ── PDF exports ───────────────────────────────────────────────────────────

    public function exportContractsPdf()
    {
        $contracts = Contract::with(['mandante', 'contractor', 'latestRiskScore'])
            ->whereIn('status', ['vigente', 'suspendido', 'en_disputa', 'terminado'])
            ->orderBy('number')
            ->get();

        $filename = 'contratos-' . now()->format('Ymd') . '.pdf';

        return Pdf::loadView('pdf.reports.contracts', compact('contracts'))
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function exportLettersPdf(Request $request)
    {
        $contractId = $request->integer('contract_id') ?: null;
        $contract   = $contractId ? Contract::with(['mandante', 'contractor'])->find($contractId) : null;

        $letters = ContractLetter::with(['contract', 'fromCompany', 'toCompany'])
            ->when($contractId, fn ($q) => $q->where('contract_id', $contractId))
            ->orderBy('issued_at')
            ->get();

        $filename = 'correspondencia-' . now()->format('Ymd') . '.pdf';

        return Pdf::loadView('pdf.reports.letters', compact('letters', 'contract'))
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}
