<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Models\Tenant;

/**
 * Fachada unificada para almacenamiento de documentos.
 * Es el ÚNICO punto de entrada para gestión de archivos en el sistema.
 * Prioridad: SharePoint/OneDrive → Google Drive → Dropbox → local.
 */
class DocumentStorageService
{
    public function __construct(
        private MicrosoftGraphService  $graph,
        private OneDrivePersonalService $onedrive,
        private GoogleDriveService     $gdrive,
        private DropboxService         $dropbox,
    ) {}

    // ── Estado ────────────────────────────────────────────────────────────────

    public function isSharePointConfigured(): bool
    {
        return $this->graph->isConfigured();
    }

    public function activeProvider(): string
    {
        if ($this->graph->isConfigured())    return 'sharepoint';
        if ($this->onedrive->isConfigured()) return 'onedrive_personal';
        if ($this->gdrive->isConfigured())   return 'google_drive';
        if ($this->dropbox->isConfigured())  return 'dropbox';
        return 'local';
    }

    // ── Subir ─────────────────────────────────────────────────────────────────

    public function upload(
        UploadedFile $file,
        Contract $contract,
        string $category,
        int $userId,
        array $relations = [],
    ): ContractDocument {
        $filename = $this->sanitizeFilename($file->getClientOriginalName());

        $attrs = [
            'contract_id'          => $contract->id,
            'contractual_event_id' => $relations['event_id']        ?? null,
            'letter_id'            => $relations['letter_id']       ?? null,
            'change_order_id'      => $relations['change_order_id'] ?? null,
            'name'                 => $filename,
            'category'             => $category,
            'file_type'            => $file->getClientMimeType(),
            'file_size'            => $file->getSize(),
            'uploaded_by'          => $userId,
        ];

        $provider = $this->activeProvider();

        try {
            $attrs = match ($provider) {
                'sharepoint'       => array_merge($attrs, $this->uploadToSharePoint($file, $contract, $category, $filename)),
                'onedrive_personal' => array_merge($attrs, $this->uploadToOneDrive($file, $contract, $category, $filename)),
                'google_drive'     => array_merge($attrs, $this->uploadToGoogleDrive($file, $contract, $category, $filename)),
                'dropbox'          => array_merge($attrs, $this->uploadToDropbox($file, $contract, $category, $filename)),
                default            => ['local_path' => $this->uploadToLocal($file, $contract, $filename)] + $attrs,
            };
        } catch (\Throwable $e) {
            Log::warning('DocumentStorageService: fallo en proveedor remoto, usando local', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);
            $attrs['local_path'] = $this->uploadToLocal($file, $contract, $filename);
        }

        return ContractDocument::create($attrs);
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function delete(ContractDocument $document): void
    {
        try {
            if ($document->sharepoint_id) {
                match ($this->activeProvider()) {
                    'sharepoint'        => $this->graph->deleteFile($document->sharepoint_id),
                    'onedrive_personal' => $this->onedrive->deleteFile($document->sharepoint_id),
                    'google_drive'      => $this->gdrive->deleteFile($document->sharepoint_id),
                    'dropbox'           => $this->dropbox->deleteFile($document->sharepoint_id),
                    default             => null,
                };
            } elseif ($document->local_path) {
                Storage::disk('local')->delete($document->local_path);
            }
        } catch (\Throwable $e) {
            Log::warning('DocumentStorageService: error al eliminar archivo físico', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);
        }

        $document->delete();
    }

    // ── Carpetas de contrato ──────────────────────────────────────────────────

    public function createContractFolders(Contract $contract): void
    {
        $tenant = Tenant::current();
        $slug   = $tenant?->slug ?? 'default';
        $number = $contract->number;

        try {
            match ($this->activeProvider()) {
                'sharepoint'        => $this->graph->createContractFolders($slug, $number),
                'onedrive_personal' => $this->onedrive->createContractFolders($slug, $number),
                'google_drive'      => $this->gdrive->createContractFolders($slug, $number),
                'dropbox'           => $this->dropbox->createContractFolders($slug, $number),
                default             => null,
            };
        } catch (\Throwable $e) {
            Log::warning('DocumentStorageService: error creando carpetas remotas', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // ── Listado ───────────────────────────────────────────────────────────────

    public function listByContract(Contract $contract, array $filters = [])
    {
        return $contract->documents()
            ->with('uploader')
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($d) => $d);
    }

    // ── Proveedores ───────────────────────────────────────────────────────────

    private function uploadToSharePoint(UploadedFile $file, Contract $contract, string $category, string $filename): array
    {
        $tenant     = Tenant::current();
        $slug       = $tenant?->slug ?? 'default';
        $remotePath = "ClaimGuard/{$slug}/{$contract->number}/{$this->categoryFolder($category)}/{$filename}";
        return $this->graph->uploadFile($file->getRealPath(), $remotePath);
    }

    private function uploadToOneDrive(UploadedFile $file, Contract $contract, string $category, string $filename): array
    {
        $tenant     = Tenant::current();
        $slug       = $tenant?->slug ?? 'default';
        $remotePath = "ClaimGuard/{$slug}/{$contract->number}/{$this->categoryFolder($category)}/{$filename}";
        return $this->onedrive->uploadFile($file->getRealPath(), $remotePath);
    }

    private function uploadToGoogleDrive(UploadedFile $file, Contract $contract, string $category, string $filename): array
    {
        $tenant     = Tenant::current();
        $slug       = $tenant?->slug ?? 'default';
        $remotePath = "ClaimGuard/{$slug}/{$contract->number}/{$this->categoryFolder($category)}/{$filename}";
        return $this->gdrive->uploadFile($file->getRealPath(), $remotePath);
    }

    private function uploadToDropbox(UploadedFile $file, Contract $contract, string $category, string $filename): array
    {
        $tenant     = Tenant::current();
        $slug       = $tenant?->slug ?? 'default';
        $remotePath = "ClaimGuard/{$slug}/{$contract->number}/{$this->categoryFolder($category)}/{$filename}";
        return $this->dropbox->uploadFile($file->getRealPath(), $remotePath);
    }

    private function uploadToLocal(UploadedFile $file, Contract $contract, string $filename): string
    {
        $tenant = Tenant::current();
        $slug   = $tenant?->slug ?? 'default';
        return $file->storeAs("tenants/{$slug}/{$contract->number}", $filename, 'local');
    }

    private function categoryFolder(string $category): string
    {
        return match ($category) {
            'carta_emitida'  => 'Cartas-Emitidas',
            'carta_recibida' => 'Cartas-Recibidas',
            'evento'         => 'Eventos',
            'orden_cambio'   => 'Ordenes-de-Cambio',
            'programa'       => 'Programa',
            'expediente'     => 'Expediente',
            default          => 'Otros',
        };
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._\-áéíóúÁÉÍÓÚñÑ ()]/', '_', $name);
    }
}
