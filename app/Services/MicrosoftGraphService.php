<?php

namespace App\Services;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftGraphService
{
    private const TOKEN_CACHE_MINUTES = 55;
    private const GRAPH_URL = 'https://graph.microsoft.com/v1.0';

    private ?TenantIntegration $integration = null;

    public function __construct()
    {
        $this->integration = TenantIntegration::forService('microsoft_graph');
    }

    public function isConfigured(): bool
    {
        return $this->integration !== null
            && $this->integration->is_active
            && !empty($this->integration->client_id)
            && !empty($this->integration->tenant_azure_id)
            && !empty($this->integration->client_secret_encrypted)
            && !empty($this->integration->site_id);
    }

    public function getAccessToken(): ?string
    {
        if (!$this->isConfigured()) return null;

        $cacheKey = 'ms_graph_token_' . $this->integration->id;

        return Cache::remember($cacheKey, now()->addMinutes(self::TOKEN_CACHE_MINUTES), function () {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->integration->tenant_azure_id}/oauth2/v2.0/token",
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->integration->client_id,
                    'client_secret' => $this->integration->client_secret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                ]
            );

            if (!$response->successful()) {
                Log::error('MicrosoftGraph: token request failed', ['response' => $response->json()]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Crea la estructura de carpetas del contrato en SharePoint.
     * /ClaimGuard/{tenant_slug}/{numero_contrato}/...
     */
    public function createContractFolders(string $tenantSlug, string $contractNumber): void
    {
        $token  = $this->getAccessToken();
        $siteId = $this->integration->site_id;

        $base = "ClaimGuard/{$tenantSlug}/{$contractNumber}";
        $folders = [
            $base,
            "{$base}/Cartas-Emitidas",
            "{$base}/Cartas-Recibidas",
            "{$base}/Eventos",
            "{$base}/Ordenes-de-Cambio",
            "{$base}/Programa",
            "{$base}/Expediente",
        ];

        foreach ($folders as $path) {
            $this->createFolderByPath($token, $siteId, $path);
        }
    }

    /**
     * Sube un archivo a SharePoint.
     * Retorna ['sharepoint_id' => ..., 'sharepoint_url' => ...]
     */
    public function uploadFile(string $localPath, string $remotePath): array
    {
        $token   = $this->getAccessToken();
        $siteId  = $this->integration->site_id;
        $content = file_get_contents($localPath);

        $response = Http::withToken($token)
            ->withBody($content, 'application/octet-stream')
            ->put(self::GRAPH_URL . "/sites/{$siteId}/drive/root:/{$remotePath}:/content");

        if (!$response->successful()) {
            throw new \RuntimeException('SharePoint upload failed: ' . $response->body());
        }

        return [
            'sharepoint_id'  => $response->json('id'),
            'sharepoint_url' => $response->json('webUrl'),
        ];
    }

    public function getFileUrl(string $sharepointId): ?string
    {
        $token  = $this->getAccessToken();
        $siteId = $this->integration->site_id;

        $response = Http::withToken($token)
            ->get(self::GRAPH_URL . "/sites/{$siteId}/drive/items/{$sharepointId}");

        return $response->successful() ? $response->json('webUrl') : null;
    }

    public function deleteFile(string $sharepointId): void
    {
        $token  = $this->getAccessToken();
        $siteId = $this->integration->site_id;

        Http::withToken($token)
            ->delete(self::GRAPH_URL . "/sites/{$siteId}/drive/items/{$sharepointId}");
    }

    private function createFolderByPath(string $token, string $siteId, string $path): void
    {
        Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->patch(self::GRAPH_URL . "/sites/{$siteId}/drive/root:/{$path}", [
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'replace',
            ]);
    }
}
