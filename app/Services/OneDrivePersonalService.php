<?php

namespace App\Services;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integración con OneDrive Personal (cuentas outlook.com / Hotmail).
 * Usa OAuth2 delegado con refresh token de larga duración.
 * El usuario autoriza una vez vía browser; el sistema renueva tokens automáticamente.
 */
class OneDrivePersonalService
{
    private const AUTHORITY   = 'https://login.microsoftonline.com/consumers/oauth2/v2.0';
    private const GRAPH_BASE  = 'https://graph.microsoft.com/v1.0/me/drive';
    private const SCOPES      = 'Files.ReadWrite offline_access User.Read';

    private ?TenantIntegration $config;

    public function __construct()
    {
        $this->config = TenantIntegration::forService('onedrive_personal');
    }

    public function isConfigured(): bool
    {
        return $this->config !== null
            && $this->config->is_active
            && !is_null($this->config->client_secret_encrypted) // refresh token
            && !is_null($this->config->client_id);             // client_id de la app
    }

    // ── OAuth ─────────────────────────────────────────────────────────────────

    /**
     * URL de autorización para redirigir al usuario (primer paso).
     */
    public function getAuthorizationUrl(string $clientId, string $redirectUri, string $state): string
    {
        $params = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'scope'         => self::SCOPES,
            'response_mode' => 'query',
            'state'         => $state,
        ]);

        return self::AUTHORITY . '/authorize?' . $params;
    }

    /**
     * Intercambia el authorization code por access_token + refresh_token.
     * Guarda el refresh_token en tenant_integrations.
     */
    public function exchangeCode(string $code, string $redirectUri): void
    {
        $integration = TenantIntegration::firstOrNew(['service' => 'onedrive_personal']);
        $clientId    = $integration->client_id;
        // client_secret de la app (tenant_azure_id lo reutilizamos para esto)
        $clientSecret = \Illuminate\Support\Facades\Crypt::decryptString(
            $integration->tenant_azure_id ?? ''
        );

        $response = Http::asForm()->post(self::AUTHORITY . '/token', [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
            'scope'         => self::SCOPES,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Error obteniendo tokens: ' . $response->body());
        }

        // Guarda el refresh_token como client_secret (campo reutilizado)
        $integration->client_secret = $response->json('refresh_token');
        $integration->is_active     = true;
        $integration->save();

        // Cachea el access_token
        Cache::put(
            'onedrive_personal_token_' . $integration->id,
            $response->json('access_token'),
            now()->addSeconds($response->json('expires_in', 3600) - 60)
        );
    }

    /**
     * Obtiene un access token válido (usa refresh_token si expiró).
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'onedrive_personal_token_' . ($this->config?->id ?? 'x');
        $cached   = Cache::get($cacheKey);
        if ($cached) return $cached;

        // Renovar usando refresh_token
        $integration  = $this->config;
        $refreshToken = $integration->client_secret;

        $clientSecret = \Illuminate\Support\Facades\Crypt::decryptString(
            $integration->tenant_azure_id ?? ''
        );

        $response = Http::asForm()->post(self::AUTHORITY . '/token', [
            'client_id'     => $integration->client_id,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
            'scope'         => self::SCOPES,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Error renovando token OneDrive: ' . $response->body());
        }

        // Actualiza el refresh_token si vino uno nuevo
        if ($response->json('refresh_token')) {
            $integration->client_secret = $response->json('refresh_token');
            $integration->save();
        }

        $token = $response->json('access_token');
        Cache::put($cacheKey, $token, now()->addSeconds($response->json('expires_in', 3600) - 60));

        return $token;
    }

    // ── Operaciones Drive ─────────────────────────────────────────────────────

    public function uploadFile(string $localPath, string $remotePath): array
    {
        $token    = $this->getAccessToken();
        $encoded  = rawurlencode($remotePath);
        $content  = file_get_contents($localPath);

        $response = Http::withToken($token)
            ->withBody($content, mime_content_type($localPath) ?: 'application/octet-stream')
            ->put(self::GRAPH_BASE . "/root:/{$remotePath}:/content");

        if (!$response->successful()) {
            throw new \RuntimeException('OneDrive upload error: ' . $response->body());
        }

        return [
            'sharepoint_id'  => $response->json('id'),
            'sharepoint_url' => $response->json('webUrl'),
        ];
    }

    public function deleteFile(string $fileId): void
    {
        $token = $this->getAccessToken();
        Http::withToken($token)->delete("https://graph.microsoft.com/v1.0/me/drive/items/{$fileId}");
    }

    public function createContractFolders(string $tenantSlug, string $contractNumber): void
    {
        $token = $this->getAccessToken();
        $base  = "ClaimGuard/{$tenantSlug}/{$contractNumber}";

        $this->ensureFolder($token, 'root', "ClaimGuard/{$tenantSlug}/{$contractNumber}");

        foreach (['Cartas-Emitidas', 'Cartas-Recibidas', 'Eventos', 'Ordenes-de-Cambio', 'Programa', 'Expediente'] as $sub) {
            Http::withToken($token)
                ->post(self::GRAPH_BASE . "/root:/{$base}:/children", [
                    'name'                              => $sub,
                    'folder'                            => new \stdClass(),
                    '@microsoft.graph.conflictBehavior' => 'rename',
                ]);
        }
    }

    private function ensureFolder(string $token, string $parent, string $path): void
    {
        Http::withToken($token)
            ->post(self::GRAPH_BASE . "/root:/{$path}:/children", [
                'name'                              => basename($path),
                'folder'                            => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename',
            ]);
    }

    public function testConnection(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OneDrive Personal no está configurado. Debes autorizar la aplicación primero.');
        }

        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->get('https://graph.microsoft.com/v1.0/me/drive');

        if (!$response->successful()) {
            throw new \RuntimeException('Error de conexión OneDrive: ' . $response->body());
        }
    }

    public function getUserInfo(): array
    {
        $token    = $this->getAccessToken();
        $response = Http::withToken($token)->get('https://graph.microsoft.com/v1.0/me');
        return $response->json() ?? [];
    }
}
