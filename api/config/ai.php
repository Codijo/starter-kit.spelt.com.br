<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IA (LLM) — camada genérica de chat/completion
    |--------------------------------------------------------------------------
    |
    | O código do produto NUNCA escolhe o modelo na chamada. Ele escolhe um
    | PERFIL semântico (default/fast/creative…) e o `AiProvider` roteia para o
    | driver certo, injetando o modelo + tuning do perfil. Trocar de fornecedor
    | (OpenRouter → Anthropic/OpenAI direto) = novo driver, sem mexer no chamador.
    | Ver App\Services\Ai\AiProvider.
    |
    | env() só aqui (config/), nunca nos serviços — quebra com config:cache.
    |
    */

    // Perfil usado quando a chamada não informa nenhum.
    'default_profile' => env('AI_PROFILE', 'default'),

    /*
    | Perfis semânticos. Cada um mapeia driver + modelo + tuning. O produto
    | referencia o PERFIL (ex.: 'creative'), nunca o modelo cru.
    */
    'profiles' => [
        'default' => [
            'driver'      => env('AI_DRIVER', 'openrouter'),
            'model'       => env('AI_MODEL', 'anthropic/claude-sonnet-4'),
            'temperature' => 0.7,
            'max_tokens'  => 1024,
        ],
        'fast' => [
            'driver'      => env('AI_DRIVER', 'openrouter'),
            'model'       => env('AI_MODEL_FAST', 'openai/gpt-4o-mini'),
            'temperature' => 0.3,
            'max_tokens'  => 512,
        ],
        'creative' => [
            'driver'      => env('AI_DRIVER', 'openrouter'),
            'model'       => env('AI_MODEL_CREATIVE', 'anthropic/claude-sonnet-4'),
            'temperature' => 0.9,
            'max_tokens'  => 1536,
        ],
    ],

    /*
    | Configuração por driver (credenciais/endpoint), reusada por qualquer perfil.
    | Um novo fornecedor entra como uma nova entrada aqui + um novo Driver.
    */
    'drivers' => [
        'openrouter' => [
            'api_key'  => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            // Headers opcionais de ranking do OpenRouter (aparecem no dashboard deles).
            'referer'  => env('OPENROUTER_REFERER', env('APP_URL')),
            'title'    => env('OPENROUTER_TITLE', env('APP_NAME')),
            'timeout'  => (int) env('AI_TIMEOUT', 30),
        ],

        // Simulador determinístico p/ testes e ambientes sem chave. Sem rede.
        'fake' => [],
    ],

    /*
    | Preço por 1M de tokens (USD) por modelo: ['input' => x, 'output' => y]. Usado para
    | calcular o custo gravado em `ai_usage_logs` (AiUsageLogger). O custo é gravado no log
    | no momento da chamada — mudar o preço aqui NÃO reescreve o histórico. Modelo sem
    | entrada → custo nulo (mas tokens continuam registrados). Mantenha em dia com o OpenRouter.
    */
    'pricing' => [
        'anthropic/claude-sonnet-4' => ['input' => 3.00, 'output' => 15.00],
        'openai/gpt-4o-mini'        => ['input' => 0.15, 'output' => 0.60],
    ],

];
