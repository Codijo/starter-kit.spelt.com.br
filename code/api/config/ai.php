<?php

// O driver decide o modelo padrão de cada perfil (ver 'profiles').
$driver = env('AI_DRIVER', 'openrouter');
$anthropic = $driver === 'anthropic';

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
    | referencia o PERFIL (ex.: 'creative'), nunca o modelo cru. `timeout`
    | no perfil substitui o do driver (resposta longa demora mais).
    |
    | O driver vem de AI_DRIVER (openrouter | anthropic) e decide o modelo
    | padrão de cada perfil: o slug do OpenRouter ("anthropic/claude-sonnet-5.5")
    | não é o id da Anthropic ("claude-opus-5-5"). Só o driver anthropic lê
    | `effort` (raciocínio) e ignora `temperature`, que os modelos atuais
    | recusam; no anthropic, o max_tokens inclui o raciocínio.
    */
    'profiles' => [
        'default' => [
            'driver'      => $driver,
            'model'       => env('AI_MODEL', $anthropic ? 'claude-opus-5-5' : 'anthropic/claude-sonnet-5.5'),
            'temperature' => 0.7,
            'max_tokens'  => $anthropic ? 8000 : 1024,
        ],
        'fast' => [
            'driver'      => $driver,
            'model'       => env('AI_MODEL_FAST', $anthropic ? 'claude-haiku-5-5' : 'openai/gpt-4o-mini'),
            'temperature' => 0.3,
            'max_tokens'  => $anthropic ? 2048 : 512,
            'effort'      => 'low',
        ],
        'creative' => [
            'driver'      => $driver,
            'model'       => env('AI_MODEL_CREATIVE', $anthropic ? 'claude-opus-5-5' : 'anthropic/claude-sonnet-5.5'),
            'temperature' => 0.9,
            'max_tokens'  => $anthropic ? 8000 : 1536,
        ],
        // Perfil do produto (ex.: resposta longa em JSON) entra aqui, com `timeout` e `effort` próprios.
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

        // Anthropic direto (Messages API), pelo SDK oficial. Chave do console da Anthropic.
        'anthropic' => [
            'api_key'  => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL'),
            'timeout'  => (int) env('AI_TIMEOUT', 30),
        ],

        // Simulador determinístico p/ testes e ambientes sem chave. Sem rede.
        'fake' => [],
    ],

    /*
    | Preço por 1M de tokens (USD) por modelo: ['input' => x, 'output' => y]. Usado para
    | calcular o custo gravado em `ai_usage_logs` (AiUsageLogger). O custo é gravado no log
    | no momento da chamada — mudar o preço aqui NÃO reescreve o histórico. Modelo sem
    | entrada → custo nulo (mas tokens continuam registrados). Mantenha em dia com o OpenRouter
    | e com a Anthropic (ids sem prefixo).
    */
    'pricing' => [
        // Anthropic direto.
        'claude-opus-5-5'             => ['input' => 4.00, 'output' => 20.00],
        'claude-sonnet-5-5'           => ['input' => 2.00, 'output' => 10.00],
        'claude-haiku-5-5'            => ['input' => 0.10, 'output' => 0.50],   // até 100 mil tokens de entrada
        // OpenRouter.
        'anthropic/claude-sonnet-5.5' => ['input' => 2.00, 'output' => 10.00],
        'anthropic/claude-sonnet-4'   => ['input' => 3.00, 'output' => 15.00],
        'openai/gpt-4o-mini'          => ['input' => 0.15, 'output' => 0.60],
    ],

];
