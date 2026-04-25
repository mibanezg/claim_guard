<?php

namespace App\Services;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Integración con Dropbox vía access token de larga duración.
 * Usa Dropbox API v2. No requiere SDK — solo HTTP.
 * Requiere: app_key (client_id) y access_token (client_secret).
 */
class DropboxService
{
    private const CONTENT_API = 'https://content.dropboxapi.com/2';
    private const API_BASE    = 'https://api.dropboxapi.com/2';

    private ?TenantIntegration $config;

    public function __construct()
    {
        $this->config = TenantIntegration::forService('dropbox');
    }

    public function isConfigured(): bool
    {
        return $this->config !== null
            && $this->config->is_active
            && !is_null($this->config->client_secret_encrypted);
    }

    public function getBasePath(): string
    {
        return rtrim($this->config?->site_id ?? '/ClaimGuard', '/');
    }

    private function token(): string
    {
        return $this->config->client_secret;
    }

    // ── Operaciones ───────────────────────────────────────────────────────────

    /**
     * Sube un archivo a Dropbox.
     * remotePath: ruta relativa sin basePath (ej: ClaimGuard/demo/001/Cartas/doc.pdf)
     * Retorna ['sharepoint_id' => path, 'sharepoint_url' => null].
     */
    public function uploadFile(string $localPath, string $remotePath): array
    {
        $dropboxPath = '/' . ltrim($remotePath, '/');
        $content     = file_get_contents($localPath);

        $response = Http::withToken($this->token())
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'path'       => $dropboxPath,
                    'mode'       => 'overwrite',
                    'autorename' => false,
                ]),
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody($content, 'application/octet-stream')
            ->post(self::CONTENT_API . '/files/upload');

        if (!$response->successful()) {
            throw new \RuntimeException('Dropbox upload error: ' . $response->body());
        }

        // Obtener enlace compartido (o crearlo)
        $shareUrl = $this->getOrCreateShareLink($dropboxPath);

        return [
            'sharepoint_id'  => $dropboxPath,
            'sharepoint_url' => $shareUrl,
        ];
    }

    public function deleteFile(string $path): void
    {
        Http::withToken($this->token())
            ->post(self::API_BASE . '/files/delete_v2', ['path' => $path]);
    }

    public function createContractFolders(string $tenantSlug, string $contractNumber): void
    {
        $base = "/{$tenantSlug}/{$contractNumber}";
        $subfolders = [
            'Cartas-Emitidas', 'Cartas-Recibidas', 'Eventos',
            'Ordenes-de-Cambio', 'Programa', 'Expediente',
        ];

        foreach ($subfolders as $sub) {
            $path = $base . '/' . $sub;
            Http::withToken($this->token())
                ->post(self::API_BASE . '/files/create_folder_v2', [
                    'path'       => $path,
                    'autorename' => false,
                ]);
            // Ignora error si la carpeta ya existe (409)
        }
    }

    public function testConnection(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Dropbox no está configurado o no está activo.');
        }

        $response = Http::withToken($this->token())
            ->post(self::API_BASE . '/users/get_current_account');

        if (!$response->successful()) {
            $err = $response->json('error_summary') ?? $response->body();
            throw new \RuntimeException("Dropbox: {$err}");
        }
    }

    private function getOrCreateShareLink(string $path): ?string
    {
        // Intenta obtener link existente
        $existing = Http::withToken($this->token())
            ->post(self::API_BASE . '/sharing/list_shared_links', [
                'path' => $path, 'direct_only' => true,
            ]);

        if ($existing->successful() && !empty($existing->json('links'))) {
            return $existing->json('links.0.url');
        }

        // Crea link público
        $created = Http::withToken($this->token())
            ->post(self::API_BASE . '/sharing/create_shared_link_with_settings', [
                'path'     => $path,
                'settings' => ['requested_visibility' => 'public'],
            ]);

        return $created->json('url');
    }
}
