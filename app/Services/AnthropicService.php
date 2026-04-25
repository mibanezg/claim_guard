<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL   = 'claude-sonnet-4-5';
    private const VERSION = '2023-06-01';

    public function isConfigured(): bool
    {
        return !empty(config('services.anthropic.key'));
    }

    /**
     * Envía un mensaje y retorna el texto de respuesta.
     * Retorna null si la API no está disponible (degradación elegante).
     */
    public function message(string $system, string $user, int $maxTokens = 2048): ?string
    {
        if (!$this->isConfigured()) {
            Log::info('AnthropicService: API key no configurada — saltando generación IA');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => self::VERSION,
                'content-type'      => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model'      => self::MODEL,
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'messages'   => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

            if (!$response->successful()) {
                Log::warning('AnthropicService: respuesta no exitosa', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json('content.0.text');

        } catch (\Throwable $e) {
            Log::warning('AnthropicService: excepción al llamar API', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
