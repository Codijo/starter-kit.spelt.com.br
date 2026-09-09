<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spelt — plataforma comercial/identidade
    |--------------------------------------------------------------------------
    |
    | O produto é CLIENTE da External API v1 do Spelt e recebe webhooks dele.
    | Ver docs/technical/starter-kit.md (§8, §13) no repo da API do Spelt.
    |
    */
    'spelt' => [
        'url'            => env('SPELT_API_URL', 'https://api.spelt.com.br'),
        'api_key'        => env('SPELT_API_KEY'),
        'webhook_secret' => env('SPELT_WEBHOOK_SECRET'),
        // TTL (dias) do token de sessão emitido após o SSO. Aqui (config), não no model —
        // env() fora de config/ quebra com config:cache.
        'session_ttl_days' => (int) env('SPELT_SESSION_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Produto (branding fixo — o kit atende UM Seller)
    |--------------------------------------------------------------------------
    */
    'product' => [
        'name' => env('PRODUCT_NAME', 'Meu Produto'),
        'logo' => env('PRODUCT_LOGO', '/img/logo.svg'),
        // Onde o Platform deste produto responde. Usada pelo `dev:token` para
        // imprimir um link clicável — cada instalação tem o próprio domínio.
        'platform_url' => rtrim((string) env('APP_PLATFORM_URL', 'http://localhost'), '/'),
    ],

];
