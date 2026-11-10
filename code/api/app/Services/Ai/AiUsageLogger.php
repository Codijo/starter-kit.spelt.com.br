<?php

namespace App\Services\Ai;

use App\Models\Ai\AiUsageLog;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\DTO\AiUsage;
use App\Services\Ai\DTO\AiUsageContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Grava uma linha em `ai_usage_logs` por chamada de IA: tokens (da resposta), custo (calculado
 * de `config('ai.pricing')`), modelo/provider, e o contexto de auditoria (quem/para quê).
 *
 * À PROVA DE FALHA: nunca lança. Um problema de log jamais quebra a chamada de IA (que o
 * cliente pode até estar pagando). Falha → warning + null.
 */
class AiUsageLogger
{
    public function log(AiResponse $response, string $driver, AiUsageContext $context): ?AiUsageLog
    {
        try {
            [$costInput, $costOutput] = $this->cost($response->model, $response->usage);
            $total = ($costInput !== null && $costOutput !== null) ? round($costInput + $costOutput, 8) : null;

            return AiUsageLog::create([
                'account_id'      => $context->accountId,
                'user_id'         => $context->userId,
                'driver'          => $driver,
                'provider'        => $this->provider($response->model, $driver),
                'model'           => $response->model,
                'action'          => $context->action,
                'context_type'    => $context->subjectType,
                'context_id'      => $context->subjectId,
                'input_tokens'    => $response->usage->promptTokens,
                'output_tokens'   => $response->usage->completionTokens,
                'cost_input_usd'  => $costInput,
                'cost_output_usd' => $costOutput,
                'total_cost_usd'  => $total,
                'metadata'        => $context->metadata,
            ]);
        } catch (Throwable $e) {
            Log::warning('[AI] Falha ao registrar uso de IA', [
                'action' => $context->action,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Provider subjacente: o prefixo do slug no OpenRouter ("anthropic/claude-sonnet-5.5" → "anthropic"); sem prefixo,
     * o próprio driver, que fala direto com o fornecedor ("claude-opus-5-5" no driver anthropic → "anthropic").
     */
    private function provider(string $model, string $driver): string
    {
        return str_contains($model, '/') ? explode('/', $model, 2)[0] : ($driver ?: 'unknown');
    }

    /**
     * Custo (USD) input/output a partir de `config('ai.pricing.{model}')` (preço por 1M tokens).
     * Modelo sem preço → [null, null] (tokens ainda são registrados).
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function cost(string $model, AiUsage $usage): array
    {
        // Do array, não por notação de ponto: o ponto de "claude-sonnet-5.5" partiria a chave.
        $price = ((array) config('ai.pricing', []))[$model] ?? null;
        if (! is_array($price)) {
            return [null, null];
        }

        $input = round($usage->promptTokens / 1_000_000 * (float) ($price['input'] ?? 0), 8);
        $output = round($usage->completionTokens / 1_000_000 * (float) ($price['output'] ?? 0), 8);

        return [$input, $output];
    }
}
