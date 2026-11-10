<?php

namespace App\Services\Ai\Drivers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\RequestOptions;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\DTO\AiRole;
use App\Services\Ai\DTO\AiUsage;
use App\Services\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Log;

/**
 * Driver da Anthropic direto (Messages API), pelo SDK oficial `anthropic-ai/sdk`. O modelo e o tuning vêm do PERFIL.
 *
 * Diferenças para o OpenRouter que o chamador não precisa saber:
 * - `system` é um campo à parte, não uma mensagem; as mensagens de sistema do pedido são juntadas nele;
 * - `temperature` não é enviada: os modelos atuais recusam (HTTP 400) qualquer valor de amostragem;
 * - não existe "modo JSON" solto: o pedido `asJson()` vira uma instrução no system;
 * - o raciocínio dos modelos atuais (Opus 5.5) não desliga, conta no `max_tokens` e é regulado por `effort` no perfil;
 * - `stop_reason` é traduzido para o vocabulário do OpenAI (`max_tokens` → `length`), que o resto do código já lê;
 * - recusa (`stop_reason = refusal`) e resposta sem texto viram `AiException`.
 */
final class AnthropicDriver implements AiDriver
{
    private const JSON_INSTRUCTION = 'Responda apenas com um objeto JSON válido, sem texto antes ou depois.';

    private const FINISH_REASONS = ['end_turn' => 'stop', 'stop_sequence' => 'stop', 'max_tokens' => 'length'];

    /**
     * @param  array  $driver  credenciais/endpoint: api_key, base_url, timeout; `transporter` (cliente PSR-18) só nos testes
     * @param  array  $profile  modelo + tuning: model, max_tokens, effort (low|medium|high|xhigh|max), thinking, timeout
     */
    public function __construct(
        private readonly array $driver,
        private readonly array $profile,
    ) {}

    public function chat(AiRequest $request): AiResponse
    {
        $apiKey = (string) ($this->driver['api_key'] ?? '');
        if ($apiKey === '') {
            throw new AiException('ANTHROPIC_API_KEY não configurada.');
        }

        $system = [];
        $messages = [];

        foreach ($request->messages as $message) {
            if ($message->role === AiRole::System) {
                $system[] = $message->content;
            } else {
                $messages[] = ['role' => $message->role->value, 'content' => $message->content];
            }
        }

        if ($request->json) {
            $system[] = self::JSON_INSTRUCTION;
        }

        $client = new Client(
            apiKey: $apiKey,
            baseUrl: ($this->driver['base_url'] ?? null) ?: null,
            requestOptions: RequestOptions::with(
                timeout: (float) ($this->profile['timeout'] ?? $this->driver['timeout'] ?? 30),
                transporter: $this->driver['transporter'] ?? null,
            ),
        );

        try {
            $response = $client->messages->create(
                maxTokens: (int) ($request->maxTokens ?? $this->profile['max_tokens'] ?? 16000),
                messages: $messages,
                model: (string) ($this->profile['model'] ?? ''),
                system: $system === [] ? null : implode("\n\n", $system),
                outputConfig: isset($this->profile['effort']) ? ['effort' => $this->profile['effort']] : null,
                thinking: $this->profile['thinking'] ?? null,
            );
        } catch (APIStatusException $e) {
            Log::warning('[AI] Anthropic retornou erro', ['status' => $e->status, 'type' => $e->getType(), 'error' => $e->getAPIMessage()]);

            throw new AiException('Anthropic retornou HTTP '.($e->status ?? '?').'.', previous: $e);
        } catch (AnthropicException $e) {
            throw new AiException('Falha ao chamar a Anthropic: '.$e->getMessage(), previous: $e);
        }

        if ($response->stopReason === 'refusal') {
            throw new AiException('A Anthropic recusou o pedido ('.($response->stopDetails?->category ?? 'sem categoria').').');
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if ($text === '') {
            Log::warning('[AI] Anthropic respondeu sem texto', ['stop_reason' => $response->stopReason, 'output_tokens' => $response->usage->outputTokens]);

            throw new AiException('Resposta da Anthropic sem conteúdo'.($response->stopReason === 'max_tokens' ? ' (o raciocínio gastou o max_tokens).' : '.'));
        }

        $input = $response->usage->inputTokens + (int) $response->usage->cacheReadInputTokens + (int) $response->usage->cacheCreationInputTokens;

        return new AiResponse(
            text: $text,
            model: $response->model,
            usage: new AiUsage(promptTokens: $input, completionTokens: $response->usage->outputTokens, totalTokens: $input + $response->usage->outputTokens),
            finishReason: self::FINISH_REASONS[$response->stopReason] ?? $response->stopReason,
            raw: (array) json_decode((string) json_encode($response), true),
        );
    }
}
