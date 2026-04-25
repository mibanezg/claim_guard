<?php

namespace App\Services;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integración con Google Drive vía Service Account.
 * Autenticación: JWT (RS256) → access token cacheado 55 min.
 * Requiere: service account email, private key PEM, folder ID raíz.
 */
class GoogleDriveService
{
    private const SCOPE     = 'https://www.googleapis.com/auth/drive';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE  = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD    = 'https://www.googleapis.com/upload/drive/v3/files';

    private ?TenantIntegration $config;

    public function __construct()
    {
        $this->config = TenantIntegration::forService('google_drive');
    }

    public function isConfigured(): bool
    {
        return $this->config !== null
            && $this->config->is_active
            && !is_null($this->config->client_id)           // service account email
            && !is_null($this->config->client_secret_encrypted); // private key
    }

    public function getRootFolderId(): ?string
    {
        return $this->config?->site_id; // folder ID compartido con la service account
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function getAccessToken(): string
    {
        $cacheKey = 'gdrive_token_' . ($this->config?->id ?? 'x');

        return Cache::remember($cacheKey, 3300, function () {
            $jwt = $this->buildJwt();

            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Google OAuth error: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    private function buildJwt(): string
    {
        $now = time();

        $header  = $this->b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->b64u(json_encode([
            'iss'   => $this->config->client_id,
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $input      = "{$header}.{$payload}";
        $privateKey = $this->config->client_secret;

        if (!openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('No se pudo firmar el JWT de Google. Verifica la private key.');
        }

        return "{$input}." . $this->b64u($signature);
    }

    private function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ── Operaciones ───────────────────────────────────────────────────────────

    /**
     * Sube un archivo a Drive en la carpeta indicada (crea subcarpetas si no existen).
     * Retorna ['sharepoint_id' => driveFileId, 'sharepoint_url' => webViewLink].
     */
    public function uploadFile(string $localPath, string $remotePath): array
    {
        $token    = $this->getAccessToken();
        $parts    = explode('/', trim($remotePath, '/'));
        $filename = array_pop($parts);
        $parentId = $this->ensureFolderPath($token, $parts);

        $metadata = json_encode(['name' => $filename, 'parents' => [$parentId]]);
        $content  = file_get_contents($localPath);
        $mime     = mime_content_type($localPath) ?: 'application/octet-stream';

        $boundary = 'gdrive_boundary_' . uniqid();
        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mime}\r\n\r\n"
            . $content . "\r\n"
            . "--{$boundary}--";

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => "multipart/related; boundary={$boundary}"])
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD . '?uploadType=multipart&fields=id,webViewLink');

        if (!$response->successful()) {
            throw new \RuntimeException('Google Drive upload error: ' . $response->body());
        }

        return [
            'sharepoint_id'  => $response->json('id'),
            'sharepoint_url' => $response->json('webViewLink'),
        ];
    }

    public function deleteFile(string $fileId): void
    {
        $token = $this->getAccessToken();

        Http::withToken($token)->delete(self::API_BASE . "/files/{$fileId}");
    }

    public function createContractFolders(string $tenantSlug, string $contractNumber): void
    {
        $token    = $this->getAccessToken();
        $rootId   = $this->getRootFolderId();
        $base     = ['ClaimGuard', $tenantSlug, $contractNumber];
        $parentId = $this->ensureFolderPath($token, $base, $rootId);

        $subfolders = [
            'Cartas-Emitidas', 'Cartas-Recibidas', 'Eventos',
            'Ordenes-de-Cambio', 'Programa', 'Expediente',
        ];

        foreach ($subfolders as $sub) {
            $this->ensureFolder($token, $sub, $parentId);
        }
    }

    // ── Helpers de carpetas ───────────────────────────────────────────────────

    private function ensureFolderPath(string $token, array $parts, ?string $rootId = null): string
    {
        $parentId = $rootId ?? $this->getRootFolderId() ?? 'root';

        foreach ($parts as $name) {
            $parentId = $this->ensureFolder($token, $name, $parentId);
        }

        return $parentId;
    }

    private function ensureFolder(string $token, string $name, string $parentId): string
    {
        // Buscar si ya existe
        $q        = urlencode("name='{$name}' and '{$parentId}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false");
        $existing = Http::withToken($token)->get(self::API_BASE . "/files?q={$q}&fields=files(id)");

        if ($existing->successful() && !empty($existing->json('files'))) {
            return $existing->json('files.0.id');
        }

        // Crear carpeta
        $created = Http::withToken($token)->post(self::API_BASE . '/files', [
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents'  => [$parentId],
        ]);

        if (!$created->successful()) {
            throw new \RuntimeException('Error creando carpeta en Drive: ' . $created->body());
        }

        return $created->json('id');
    }

    public function testConnection(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google Drive no está configurado o no está activo.');
        }

        $token    = $this->getAccessToken();
        $response = Http::withToken($token)->get(self::API_BASE . '/about?fields=user');

        if (!$response->successful()) {
            throw new \RuntimeException('Error de conexión con Google Drive: ' . $response->body());
        }
    }
}
