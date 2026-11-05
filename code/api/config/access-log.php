<?php

/*
|--------------------------------------------------------------------------
| Registro de acesso (Marco Civil, art. 15)
|--------------------------------------------------------------------------
|
| O produto guarda data, hora e IP de uso da API autenticada por 7 meses (App\Models\Core\Account\AccessLog).
| O login fica no Spelt, que é quem o vê; aqui é o uso, que o Spelt não vê.
|
*/

return [

    // Uma gravação por usuário, IP e porta a cada N segundos: o `last_seen_at` tem essa precisão, e a
    // API não escreve no banco a cada requisição.
    'throttle_seconds' => 60,

    // Cabeçalho com a porta lógica de origem do cliente (CGNAT). Vazio = porta desconhecida (0). O
    // Cloudflare não manda a porta por padrão; ativar é configuração lá, e então o nome vai aqui.
    'port_header' => env('ACCESS_LOG_PORT_HEADER'),

    // O IP é o `$request->ip()`, que o par TrustProxies + ResolveCloudflareClientIp já leva ao cliente
    // (config/trustedproxy.php; `php artisan proxies:audit` prova).

];
