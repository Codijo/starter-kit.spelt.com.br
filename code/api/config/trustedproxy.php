<?php

/*
|--------------------------------------------------------------------------
| Proxies confiáveis
|--------------------------------------------------------------------------
|
| Quem a aplicação aceita como intermediário ao ler X-Forwarded-For,
| X-Forwarded-Proto, X-Forwarded-Host e X-Forwarded-Port. É o arquivo que o
| `Illuminate\Http\Middleware\TrustProxies` consulta (stack global padrão do
| Laravel; o `ResolveCloudflareClientIp` vem logo depois — ver bootstrap/app.php).
|
| Portado da API do Spelt em 2026-11-04 (que o portou da iPORTO). Substitui o
| `trustProxies(at: '*')`, que confiava só no proxy do Coolify: o IP que sobrava
| era o da borda do Cloudflare, e o registro de acesso (Marco Civil) guardaria
| o endereço do Cloudflare no lugar do cliente.
|
| ── O caminho de uma requisição ───────────────────────────────────────────
|
|   cliente → borda do Cloudflare → proxy do Coolify (Traefik) → nginx da imagem → php-fpm
|
| O nginx da imagem NÃO usa `real_ip_header`: quem resolve o cliente é esta camada.
|
| ── Por que `REMOTE_ADDR` em vez da faixa da rede do Docker ───────────────
|
| `REMOTE_ADDR` é um token que o TrustProxies troca pelo endereço de quem abriu
| a conexão. No Coolify é sempre o proxy, e o token evita fixar a sub-rede do
| Docker — que muda entre bridge e swarm, e entre versões do Coolify. Chute
| errado de sub-rede não dá erro: só volta a devolver o IP do proxy, em silêncio.
|
| ⚠️ O que torna isso seguro é a porta 80 do container NÃO ser publicada: o
|    único jeito de falar com ela é pela rede interna, via proxy. Um "Ports
|    Mapping" no Coolify que exponha a porta no host deixa qualquer um forjar o
|    X-Forwarded-For. Mapear porta na API é decisão que passa por aqui primeiro.
|
| ── Faixas do Cloudflare ──────────────────────────────────────────────────
|
| Versionadas de propósito: ler a lista pela rede no boot transformaria uma
| oscilação do cloudflare.com em deploy travado. O preço é drift silencioso —
| faixa nova devolve o IP da BORDA no lugar do cliente. Quem pergunta por isso:
|
|   php artisan proxies:audit --upstream     (agendado toda segunda — routes/console.php)
*/

// Conferido contra https://www.cloudflare.com/ips-v4 e https://www.cloudflare.com/ips-v6 em
// 2026-10-11 (`proxies:audit --upstream`).
$cloudflare = [
    // IPv4
    '173.245.48.0/20',
    '103.21.244.0/22',
    '103.22.200.0/22',
    '103.31.4.0/22',
    '141.101.64.0/18',
    '108.162.192.0/18',
    '190.93.240.0/20',
    '188.114.96.0/20',
    '197.234.240.0/22',
    '198.41.128.0/17',
    '162.158.0.0/15',
    '104.16.0.0/13',
    '104.24.0.0/14',
    '172.64.0.0/13',
    '131.0.72.0/22',

    // IPv6
    '2400:cb00::/32',
    '2606:4700::/32',
    '2803:f800::/32',
    '2405:b500::/32',
    '2405:8100::/32',
    '2a06:98c0::/29',
    '2c0f:f248::/32',
];

// Escape hatch para topologia diferente (um balanceador extra na frente). Lista separada por
// vírgula; quando preenchida, SUBSTITUI o default — inclusive as faixas do Cloudflare.
$configured = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', ''))
)));

return [
    'proxies' => $configured ?: array_merge(['REMOTE_ADDR'], $cloudflare),

    // Consultada separadamente pelo `proxies:audit` e pelo `ResolveCloudflareClientIp`, que precisa
    // saber se a requisição chegou mesmo pela borda antes de acreditar no CF-Connecting-IP.
    'cloudflare' => $cloudflare,

    // Só para o `proxies:audit` relatar de onde veio a lista (env() fora de config/ quebra com config:cache).
    'overridden' => $configured !== [],
];
