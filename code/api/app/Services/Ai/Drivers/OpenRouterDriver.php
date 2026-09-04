<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\DTO\AiUsage;
use App\Services\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Driver do OpenRouter (API OpenAI-compatible: POST /chat/completions).
 * O modelo e o tuning vêm do PERFIL (`$profile`), não da chamada.
 * Ver https://openrouter.ai/docs/quickstart.
 */
final class OpenRouterDriver implements AiDriver
{
    /**
     * @param array $driver  credenciais/endpoint: api_key, base_url, referer, title, timeout
     * @param array $profile modelo + tuning: model, temperature, max_tokens
     */
    public function __construct(
        private readonly array $driver,
        private readonly array $profile,
    ) {}

    public function chat(AiRequest $request): AiResponse
    {
        $apiKey = (string) ($this->driver['api_key'] ?? '');
        if ($apiKey === '') {
            throw new AiException('OPENROUTER_API_KEY não configurada.');
        }

        $payload = array_filter([
            'model'       => $this->profile['model'] ?? null,
            'messages'    => $request->messagesArray(),
            'temperature' => $request->temperature ?? $this->profile['temperature'] ?? null,
            'max_tokens'  => $request->maxTokens ?? $this->profile['max_tokens'] ?? null,
        ], fn ($v) => $v !== null);

        if ($request->json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->withHeaders(array_filter([
                    'HTTP-Referer' => $this->driver['referer'] ?? null,
                    'X-Title'      => $this->driver['title'] ?? null,
                ]))
                ->timeout((int) ($this->driver['timeout'] ?? 30))
                ->post($this->endpoint(), $payload);
        } catch (Throwable $e) {
            throw new AiException('Falha ao chamar o OpenRouter: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('[AI] OpenRouter retornou erro', [
                'status' => $response->status(),
                'error'  => $response->json('error'),
            ]);

            throw new AiException('OpenRouter retornou HTTP '.$response->status().'.');
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new AiException('Resposta do OpenRouter sem conteúdo.');
        }

        return new AiResponse(
            text: (string) $text,
            model: (string) ($data['model'] ?? $this->profile['model'] ?? ''),
            usage: AiUsage::fromArray($data['usage'] ?? null),
            finishReason: $data['choices'][0]['finish_reason'] ?? null,
            raw: is_array($data) ? $data : [],
        );
    }

    private function endpoint(): string
    {
        $base = rtrim((string) ($this->driver['base_url'] ?? 'https://openrouter.ai/api/v1'), '/');

        return $base.'/chat/completions';
    }
}
