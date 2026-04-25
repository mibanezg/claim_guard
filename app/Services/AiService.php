<?php

namespace App\Services;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fachada unificada para proveedores de IA.
 * Soporta Anthropic, OpenAI y Google Gemini.
 * Carga la configuración desde tenant_integrations (service = 'ai').
 */
class AiService
{
    public const PROVIDERS = [
        'anthropic' => [
            'label'  => 'Anthropic (Claude)',
            'models' => [
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (recomendado)',
                'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (alternativo)',
                'claude-sonnet-4-6'         => 'Claude Sonnet 4.6',
                'claude-opus-4-7'           => 'Claude Opus 4.7',
            ],
            'default_model' => 'claude-haiku-4-5-20251001',
        ],
        'openai' => [
            'label'  => 'OpenAI (GPT)',
            'models' => [
                'gpt-4o'      => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
            ],
            'default_model' => 'gpt-4o',
        ],
        'google' => [
            'label'  => 'Google (Gemini)',
            'models' => [
                'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite (free tier)',
                'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
                'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
                'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
            ],
            'default_model' => 'gemini-2.0-flash-lite',
        ],
    ];

    private ?TenantIntegration $config;

    public function __construct()
    {
        $this->config = TenantIntegration::forService('ai');
    }

    // ── Estado ────────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return $this->config !== null
            && $this->config->is_active
            && !is_null($this->config->client_secret);
    }

    public function getProvider(): string
    {
        return $this->config?->client_id ?? 'anthropic';
    }

    public function getModel(): string
    {
        return $this->config?->site_id
            ?? self::PROVIDERS[$this->getProvider()]['default_model']
            ?? '';
    }

    // ── Llamada unificada ─────────────────────────────────────────────────────

    /**
     * Envía un mensaje al proveedor configurado y retorna el texto de respuesta.
     *
     * @param  string  $system  Prompt de sistema
     * @param  string  $user    Prompt del usuario
     * @return string|null      Respuesta o null si falla
     */
    public function complete(string $system, string $user, int $maxTokens = 2048): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            return match ($this->getProvider()) {
                'anthropic' => $this->callAnthropic($system, $user, $maxTokens),
                'openai'    => $this->callOpenAi($system, $user, $maxTokens),
                'google'    => $this->callGemini($system, $user),
                default     => null,
            };
        } catch (\Throwable $e) {
            Log::warning('AiService: error en llamada a IA', [
                'provider' => $this->getProvider(),
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verifica la conexión con el proveedor enviando un mensaje mínimo.
     * Lanza excepción con el error real del proveedor.
     */
    public function testConnection(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('La integración de IA no está configurada o no está activa.');
        }

        // Llamada directa (sin captura de excepción) para exponer el error real
        $result = match ($this->getProvider()) {
            'anthropic' => $this->callAnthropic('Eres un asistente.', 'Responde solo: OK', 16),
            'openai'    => $this->callOpenAi('Eres un asistente.', 'Responde solo: OK', 16),
            'google'    => $this->callGemini('Eres un asistente.', 'Responde solo: OK'),
            default     => throw new \RuntimeException('Proveedor desconocido.'),
        };

        if (empty($result)) {
            throw new \RuntimeException('El proveedor respondió pero sin contenido de texto.');
        }
    }

    // ── Anthropic ─────────────────────────────────────────────────────────────

    private function callAnthropic(string $system, string $user, int $maxTokens = 2048): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->config->client_secret,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->getModel(),
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Anthropic API error: ' . $response->body());
        }

        return $response->json('content.0.text') ?? '';
    }

    // ── OpenAI ────────────────────────────────────────────────────────────────

    private function callOpenAi(string $system, string $user, int $maxTokens = 2048): string
    {
        $response = Http::withToken($this->config->client_secret)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => $this->getModel(),
                'max_tokens' => $maxTokens,
                'messages'   => [
                    ['role' => 'system',  'content' => $system],
                    ['role' => 'user',    'content' => $user],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    // ── Google Gemini ─────────────────────────────────────────────────────────

    private function callGemini(string $system, string $user): string
    {
        $model    = $this->getModel();
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::withQueryParameters(['key' => $this->config->client_secret])
            ->timeout(60)
            ->post($endpoint, [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents'           => [['parts' => [['text' => $user]]]],
            ]);

        if (!$response->successful()) {
            $err = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("Gemini ({$model}): {$err}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        // Gemini puede bloquear por seguridad y retornar candidates vacío
        if ($text === null) {
            $reason = $response->json('candidates.0.finishReason')
                ?? $response->json('promptFeedback.blockReason')
                ?? 'respuesta vacía';
            throw new \RuntimeException("Gemini retornó sin texto. Razón: {$reason}. Body: " . substr($response->body(), 0, 300));
        }

        return $text;
    }
}
