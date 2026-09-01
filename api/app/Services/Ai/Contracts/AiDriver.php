<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\Exceptions\AiException;

/**
 * Contrato genérico de um fornecedor de LLM. Toda implementação (OpenRouter,
 * futuramente Anthropic/OpenAI diretos, ou o Fake de teste) recebe o modelo +
 * tuning já resolvidos do perfil no construtor — a chamada só passa as mensagens.
 */
interface AiDriver
{
    /** @throws AiException em falha de configuração, rede ou resposta malformada. */
    public function chat(AiRequest $request): AiResponse;
}
