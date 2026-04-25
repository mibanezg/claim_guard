<?php

namespace App\Services;

use App\Models\TenantIntegration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class ContractPdfService
{
    private const MAX_CHARS      = 80_000;
    private const MIN_TEXT_CHARS = 200;   // menos que esto → asumir escaneado

    public function extractText(UploadedFile $file): string
    {
        // Intento 1: extracción digital (rápida, sin costo)
        $text = $this->extractDigital($file);

        if (strlen(trim($text)) >= self::MIN_TEXT_CHARS) {
            return $this->normalize($text);
        }

        // Intento 2: OCR vía Anthropic PDF API (PDFs escaneados)
        Log::info('ContractPdfService: texto digital insuficiente, usando OCR vía Anthropic');
        $text = $this->extractWithAnthropicOcr($file);

        if (!$text) {
            throw new \RuntimeException(
                'No se pudo extraer el texto del PDF. ' .
                'Verifica que la IA (Anthropic) esté configurada en Ajustes para procesar PDFs escaneados.'
            );
        }

        return $this->normalize($text);
    }

    // ── Extracción digital ─────────────────────────────────────────────────────

    private function extractDigital(UploadedFile $file): string
    {
        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($file->getRealPath());
            return $pdf->getText();
        } catch (\Throwable $e) {
            Log::debug('ContractPdfService: extracción digital falló', ['error' => $e->getMessage()]);
            return '';
        }
    }

    // ── OCR vía Anthropic ─────────────────────────────────────────────────────

    private function extractWithAnthropicOcr(UploadedFile $file): ?string
    {
        $integration = TenantIntegration::forService('ai');

        if (!$integration || !$integration->is_active || !$integration->client_secret) {
            return null;
        }

        // Solo disponible con Anthropic (soporte nativo de PDF con visión)
        if ($integration->client_id !== 'anthropic') {
            return null;
        }

        try {
            $pdfBase64 = base64_encode(file_get_contents($file->getRealPath()));
            $fileSizeMb = $file->getSize() / 1024 / 1024;

            if ($fileSizeMb > 30) {
                throw new \RuntimeException('El PDF supera los 30 MB. Usa un archivo más pequeño para OCR.');
            }

            $response = Http::withHeaders([
                'x-api-key'         => $integration->client_secret,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta'    => 'pdfs-2024-09-25',
                'content-type'      => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001', // más económico para OCR
                'max_tokens' => 8192,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'document',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => 'application/pdf',
                                'data'       => $pdfBase64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Extrae todo el texto de este documento contractual. ' .
                                      'Devuelve únicamente el texto extraído, preservando la estructura de cláusulas y numeración. ' .
                                      'No agregues comentarios ni explicaciones.',
                        ],
                    ],
                ]],
            ]);

            if (!$response->successful()) {
                Log::warning('ContractPdfService: Anthropic OCR error', ['body' => $response->body()]);
                return null;
            }

            return $response->json('content.0.text') ?? null;

        } catch (\Throwable $e) {
            Log::warning('ContractPdfService: OCR vía Anthropic falló', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function normalize(string $text): string
    {
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        if (strlen($text) > self::MAX_CHARS) {
            $text = substr($text, 0, self::MAX_CHARS)
                . "\n\n[... documento truncado por longitud ...]";
        }

        return $text;
    }

    /**
     * Recorta el texto para incluirlo en prompts IA.
     */
    public static function forPrompt(?string $text, int $maxChars = 20_000): string
    {
        if (!$text) return '';
        if (strlen($text) <= $maxChars) return $text;
        return substr($text, 0, $maxChars) . "\n\n[... texto truncado ...]";
    }

    /**
     * Construye el bloque de contexto contractual completo a partir de los
     * documentos constitutivos del contrato, para incluir en prompts IA.
     * Cada documento aporta hasta $charsPerDoc caracteres.
     */
    public static function buildCorpusContext(iterable $documents, int $charsPerDoc = 15_000): string
    {
        $sections = [];

        foreach ($documents as $doc) {
            if (empty($doc->extracted_text)) continue;

            $label   = \App\Models\ContractDocument::CONSTITUTIVE_LABELS[$doc->category] ?? $doc->category;
            $name    = $doc->name;
            $text    = self::forPrompt($doc->extracted_text, $charsPerDoc);

            $sections[] = "=== {$label}: {$name} ===\n{$text}";
        }

        if (empty($sections)) return '';

        return "CUERPO CONTRACTUAL (usa estos documentos para citar cláusulas específicas):\n"
            . implode("\n\n", $sections);
    }
}
