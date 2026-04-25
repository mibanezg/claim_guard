<?php

namespace App\Jobs;

use App\Models\ContractDocument;
use App\Models\ContractLetter;
use App\Models\ContractUser;
use App\Services\AiService;
use App\Services\ContractPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class GenerateLetterDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        private int    $letterId,
        private int    $tenantId,
        private string $userDescription,
        private int    $userId = 0,
    ) {}

    public function handle(AiService $ai): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $tenant->makeCurrent();

        $letter = ContractLetter::with([
            'contract.mandante',
            'contract.contractor',
            'contract.events' => fn ($q) => $q->latest('occurred_at')->take(5),
            'contract.letters' => fn ($q) => $q->where('id', '!=', $this->letterId)->latest()->take(3),
        ])->find($this->letterId);

        if (!$letter) return;

        $contract = $letter->contract;

        // Determinar perspectiva: ¿quién escribe?
        $contractUser = $this->userId
            ? ContractUser::where('contract_id', $contract->id)
                ->where('user_id', $this->userId)
                ->first()
            : null;

        $isCounterpart = $contractUser?->role === 'counterpart';
        $writingAs     = $isCounterpart ? $contract->mandante : $contract->contractor;
        $writingTo     = $isCounterpart ? $contract->contractor : $contract->mandante;
        $partyLabel    = $isCounterpart ? 'Mandante' : 'Contratista';

        $system = <<<SYSTEM
Eres un experto redactor de contratos de construcción y minería bajo legislación chilena.
Tu especialidad es la redacción de correspondencia contractual formal: cartas de notificación,
reserva de derechos, respuestas y actas. Redactas con precisión técnica-legal, citas las
cláusulas específicas del contrato cuando corresponde, y usas el tono formal apropiado
para contratos de gran envergadura. Cuando se proporciona el texto del contrato, refiérete
a él para citar cláusulas específicas, plazos contractuales y definiciones relevantes.
Respondes siempre en español formal chileno.
SYSTEM;

        // Cláusulas estructuradas ingresadas manualmente
        $clausesText = $contract->clauses
            ? collect($contract->clauses)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n")
            : 'No hay cláusulas registradas manualmente.';

        $recentEvents = $contract->events->map(fn ($e) =>
            "- [{$e->occurred_at->format('d/m/Y')}] {$e->type}: {$e->description}"
        )->implode("\n") ?: 'Sin eventos recientes.';

        $relatedLetters = $contract->letters->map(fn ($l) =>
            "- {$l->letter_number} ({$l->type}): {$l->subject}"
        )->implode("\n") ?: 'Sin correspondencia previa.';

        // Cuerpo contractual completo: todos los documentos constitutivos
        $corpusDocs = ContractDocument::where('contract_id', $contract->id)
            ->constitutive()
            ->get();

        $corpusSection = ContractPdfService::buildCorpusContext($corpusDocs, 12_000);

        // Fallback al campo contract_text legacy si no hay corpus cargado
        if (!$corpusSection && $contract->contract_text) {
            $legacyText    = ContractPdfService::forPrompt($contract->contract_text, 20_000);
            $corpusSection = "=== Contrato Base ===\n{$legacyText}";
        }

        $contractTextSection = $corpusSection
            ? "CUERPO CONTRACTUAL (cita documentos y cláusulas específicas cuando corresponda):\n---\n{$corpusSection}\n---\n\n"
            : '';

        $writingAsName = $writingAs?->name ?? $partyLabel;
        $writingToName = $writingTo?->name ?? ($isCounterpart ? 'Contratista' : 'Mandante');

        $user = <<<USER
Contrato: {$contract->number} — {$contract->name}
Mandante: {$contract->mandante?->name}
Contratista: {$contract->contractor?->name}

PERSPECTIVA: Redactas en nombre de **{$writingAsName}** ({$partyLabel}) dirigida a **{$writingToName}**.
Toda la carta debe estar escrita desde la posición de {$writingAsName}.

Tipo de carta: {$letter->type}
Asunto: {$letter->subject}

{$contractTextSection}Cláusulas clave registradas en el sistema:
{$clausesText}

Últimos eventos contractuales:
{$recentEvents}

Cartas previas relacionadas:
{$relatedLetters}

Instrucción del usuario:
{$this->userDescription}

Redacta el borrador completo de la carta desde la perspectiva de {$writingAsName}. Si el texto del contrato está disponible, cita los números de cláusula específicos relevantes.
USER;

        $draft = $ai->complete($system, $user, 2048);

        if ($draft) {
            $letter->update([
                'content_draft' => $draft,
                'ai_generated'  => true,
            ]);
            Log::info('GenerateLetterDraftJob: borrador generado', ['letter_id' => $this->letterId]);
        } else {
            Log::warning('GenerateLetterDraftJob: IA no disponible', ['letter_id' => $this->letterId]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateLetterDraftJob: falló tras reintentos', [
            'letter_id' => $this->letterId,
            'error'     => $e->getMessage(),
        ]);
    }
}
