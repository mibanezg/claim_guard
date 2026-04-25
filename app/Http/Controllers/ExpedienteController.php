<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClaimSummaryJob;
use App\Jobs\GenerateClaimPdfJob;
use App\Models\Contract;
use App\Services\AiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Multitenancy\Models\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpedienteController extends Controller
{
    public function __construct(private AiService $ai) {}

    public function index(Request $request): Response
    {
        $contracts = Contract::with(['mandante', 'contractor', 'latestRiskScore'])
            ->where('status', 'en_disputa')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($c) => [
                'id'                    => $c->id,
                'name'                  => $c->name,
                'number'                => $c->number,
                'mandante'              => $c->mandante?->name,
                'contractor'            => $c->contractor?->name,
                'risk_level'            => $c->latestRiskScore?->score_level,
                'risk_value'            => $c->latestRiskScore?->score_value,
                'claim_generated_at'    => $c->claim_generated_at?->setTimezone('America/Santiago')->format('d/m/Y H:i'),
                'has_pdf'               => !is_null($c->claim_pdf_sharepoint_id) || !is_null($c->claim_pdf_path),
                'has_summary'           => !is_null($c->claim_summary),
                'claim_summary_preview' => $c->claim_summary ? (function($text) {
                    // Limpia markdown para preview legible
                    $text = preg_replace('/^#{1,6}\s*/m', '', $text);
                    $text = preg_replace('/\*{1,2}([^*\n]+)\*{1,2}/', '$1', $text);
                    $text = preg_replace('/^\|.+\|$/m', '', $text);
                    $text = preg_replace('/^[-|: ]+$/m', '', $text);
                    $text = preg_replace('/^---+$/m', '', $text);
                    $text = trim(preg_replace('/\n{3,}/', "\n\n", $text));
                    return \Str::limit($text, 500);
                })($c->claim_summary) : null,
                'claim_pdf_sharepoint_url' => $c->claim_pdf_sharepoint_url,
            ]);

        return Inertia::render('Expediente/Index', [
            'contracts'    => $contracts,
            'ai_available' => $this->ai->isConfigured(),
            'flash'        => session()->only(['success', 'error']),
        ]);
    }

    /**
     * Inicia el proceso: genera resumen IA → luego genera PDF.
     * Si la IA no está disponible, genera el PDF directamente sin resumen.
     */
    public function generate(Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        if ($contract->status !== 'en_disputa') {
            return back()->with('error', 'Solo se puede generar el expediente para contratos en disputa.');
        }

        $tenant = Tenant::current();

        if ($this->ai->isConfigured()) {
            // Con IA: genera resumen primero, luego el PDF se dispara desde GenerateClaimSummaryJob
            GenerateClaimSummaryJob::dispatch($contract->id, $tenant->id);
            return back()->with('success', 'Generación iniciada. El expediente estará listo en unos minutos.');
        } else {
            // Sin IA: genera PDF directamente
            GenerateClaimPdfJob::dispatch($contract->id, $tenant->id);
            return back()->with('success', 'Generando expediente PDF. Estará disponible en unos momentos.');
        }
    }

    /**
     * Descarga el PDF si está almacenado localmente.
     */
    public function download(Contract $contract): \Symfony\Component\HttpFoundation\BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $contract);

        if ($contract->claim_pdf_sharepoint_url) {
            return redirect($contract->claim_pdf_sharepoint_url);
        }

        if ($contract->claim_pdf_path) {
            $absPath = storage_path('app/' . $contract->claim_pdf_path);
            if (file_exists($absPath)) {
                return response()->download($absPath, "expediente-claim-{$contract->number}.pdf", [
                    'Content-Type' => 'application/pdf',
                ]);
            }
        }

        return back()->with('error', 'El PDF del expediente no está disponible. Genéralo nuevamente.');
    }
}
