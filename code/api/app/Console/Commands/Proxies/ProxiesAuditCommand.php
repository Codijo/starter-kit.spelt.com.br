<?php

namespace App\Console\Commands\Proxies;

use App\Http\Middleware\ResolveCloudflareClientIp;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;

/**
 * Prova que `$request->ip()` chega ao IP do cliente, e não ao de um proxy.
 *
 * ── Por que este comando existe ───────────────────────────────────────────
 *
 * Portado da API do Spelt em 2026-11-04 (que o portou da iPORTO). Sem este par de middlewares,
 * `$request->ip()` é a borda do Cloudflare ou o proxy do Coolify em TODA requisição: o registro de
 * acesso (Marco Civil) guarda o endereço errado e todo `throttle` vira um balde único.
 * Configuração errada não grita, só some. Regra que não grita precisa de quem pergunte por ela.
 *
 * ── O que ele confere, sem tocar na rede ──────────────────────────────────
 *
 * 1. Os dois middlewares estão no stack GLOBAL e na ordem certa. Fora de lá,
 *    ou invertidos, nada mais aqui importa.
 * 2. A lista de proxies confiáveis não está vazia.
 * 3. A resolução da cadeia, em cinco cenários reais — incluindo os dois que
 *    importam: o Coolify com a configuração padrão (que apaga o
 *    X-Forwarded-For da borda) e a tentativa de forjar o IP batendo na origem
 *    por fora do Cloudflare.
 *
 * Nada de rede neste caminho: dá para rodar no boot sem transformar oscilação
 * do cloudflare.com em deploy travado.
 *
 * ── E com `--upstream`, o drift da lista ──────────────────────────────────
 *
 * As faixas do Cloudflare são versionadas em config/trustedproxy.php. Faixa
 * nova publicada lá fora não dá erro aqui: só volta a devolver o IP da borda,
 * em silêncio, para quem entrar por ela. `--upstream` compara a lista do
 * repositório com a publicada e diz exatamente o que mudou.
 *
 * ── Como usar ─────────────────────────────────────────────────────────────
 *
 *   php artisan proxies:audit              confere a cadeia (sem rede)
 *   php artisan proxies:audit --upstream   confere também as faixas publicadas
 *
 * Sai com 1 quando algum cenário resolve errado ou quando a lista divergiu.
 */
class ProxiesAuditCommand extends Command
{
    protected $signature = 'proxies:audit {--upstream : Compara as faixas do Cloudflare com as publicadas (requer rede)}';

    protected $description = 'Prova que a cadeia de proxies resolve até o IP do cliente';

    /**
     * Endereços de teste. Os do "cliente" e do "atacante" vêm das faixas
     * reservadas para documentação (RFC 5737): não pertencem a ninguém e nunca
     * vão colidir com faixa do Cloudflare.
     */
    private const PROXY = '10.0.1.5';          // proxy do Coolify, na rede do Docker

    private const EDGE = '172.68.1.1';         // borda do Cloudflare (dentro de 172.64.0.0/13)

    private const CLIENT = '203.0.113.7';      // o IP que a aplicação precisa registrar

    private const ATTACKER = '198.51.100.9';   // quem bate na origem por fora do Cloudflare

    private const SPOOFED = '203.0.113.200';   // o IP que ele tenta plantar

    public function handle(): int
    {
        $failed = false;

        $failed = ! $this->checkMiddlewareOrder() || $failed;
        $failed = ! $this->checkTrustedList() || $failed;
        $failed = ! $this->checkChainResolution() || $failed;

        if ($this->option('upstream')) {
            $failed = ! $this->checkUpstreamRanges() || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * O stack global, na ordem em que o Kernel o declara.
     *
     * O contrato `Illuminate\Contracts\Http\Kernel` não declara
     * `getGlobalMiddleware()` — quem declara é a classe concreta. A anotação
     * aqui é o que mantém a análise estática junto do que roda.
     *
     * @return array<int, string>
     */
    private function globalMiddleware(): array
    {
        /** @var Kernel $kernel */
        $kernel = app(HttpKernel::class);

        return array_values($kernel->getGlobalMiddleware());
    }

    /**
     * O `TrustProxies` precisa vir antes do `ResolveCloudflareClientIp`: o
     * segundo decide o que fazer olhando o `$request->ip()` que o primeiro
     * resolveu. Invertidos, o segundo lê o IP do proxy e nunca dispara.
     */
    private function checkMiddlewareOrder(): bool
    {
        $stack = $this->globalMiddleware();

        $trust = array_search(TrustProxies::class, $stack, true);
        $cloudflare = array_search(ResolveCloudflareClientIp::class, $stack, true);

        if ($trust === false) {
            $this->error('  middlewares: TrustProxies não está no stack global (bootstrap/app.php).');
            $this->line('               Sem ele, $request->ip() devolve o proxy do Coolify em toda requisição.');

            return false;
        }

        if ($cloudflare === false) {
            $this->error('  middlewares: ResolveCloudflareClientIp não está no stack global (bootstrap/app.php).');

            return false;
        }

        if ($trust > $cloudflare) {
            $this->error('  middlewares: ordem invertida — TrustProxies tem que vir ANTES de ResolveCloudflareClientIp.');

            return false;
        }

        $this->line('  <info>middlewares</info>: TrustProxies e ResolveCloudflareClientIp no stack global, na ordem');

        return true;
    }

    private function checkTrustedList(): bool
    {
        $proxies = (array) config('trustedproxy.proxies', []);
        $cloudflare = (array) config('trustedproxy.cloudflare', []);

        if ($proxies === []) {
            $this->error('  confiáveis: a lista está VAZIA (config/trustedproxy.proxies).');
            $this->line('              Confira TRUSTED_PROXIES no destino: preenchida, ela substitui o default.');

            return false;
        }

        $source = config('trustedproxy.overridden') ? 'env TRUSTED_PROXIES' : 'default do config';

        $this->line(sprintf(
            '  <info>confiáveis</info>: %s %s via <options=bold>%s</> (%s faixas do Cloudflare versionadas)',
            number_format(count($proxies), 0, ',', '.'),
            count($proxies) === 1 ? 'entrada' : 'entradas',
            $source,
            number_format(count($cloudflare), 0, ',', '.')
        ));

        if (! in_array('REMOTE_ADDR', $proxies, true)) {
            $this->warn('              Sem o token REMOTE_ADDR: o proxy do Coolify precisa estar coberto por');
            $this->warn('              alguma faixa da lista, senão a cadeia para nele.');
        }

        return true;
    }

    /**
     * Roda os middlewares de verdade, pelos nomes encontrados no Kernel, sobre
     * requisições sintéticas. É a diferença entre "a configuração parece certa"
     * e "a cadeia resolve no cliente".
     */
    private function checkChainResolution(): bool
    {
        $scenarios = [
            [
                'name' => 'borda repassa o X-Forwarded-For (Traefik com trustedIPs)',
                'server' => [
                    'REMOTE_ADDR' => self::PROXY,
                    'HTTP_X_FORWARDED_FOR' => self::CLIENT.', '.self::EDGE,
                    'HTTP_X_REAL_IP' => self::EDGE,
                    'HTTP_CF_CONNECTING_IP' => self::CLIENT,
                ],
                'expected' => self::CLIENT,
            ],
            [
                'name' => 'borda NÃO repassa — o Traefik apagou (padrão do Coolify)',
                'server' => [
                    'REMOTE_ADDR' => self::PROXY,
                    'HTTP_X_FORWARDED_FOR' => self::EDGE,
                    'HTTP_X_REAL_IP' => self::EDGE,
                    'HTTP_CF_CONNECTING_IP' => self::CLIENT,
                ],
                'expected' => self::CLIENT,
            ],
            [
                'name' => 'X-Forwarded-For não chega de jeito nenhum',
                'server' => [
                    'REMOTE_ADDR' => self::PROXY,
                    'HTTP_X_REAL_IP' => self::EDGE,
                    'HTTP_CF_CONNECTING_IP' => self::CLIENT,
                ],
                'expected' => self::CLIENT,
            ],
            [
                'name' => 'cliente forja X-Forwarded-For passando pelo Cloudflare',
                'server' => [
                    'REMOTE_ADDR' => self::PROXY,
                    'HTTP_X_FORWARDED_FOR' => self::SPOOFED.', '.self::CLIENT.', '.self::EDGE,
                    'HTTP_X_REAL_IP' => self::EDGE,
                    'HTTP_CF_CONNECTING_IP' => self::CLIENT,
                ],
                'expected' => self::CLIENT,
            ],
            [
                'name' => 'bypass do Cloudflare com CF-Connecting-IP forjado',
                'server' => [
                    'REMOTE_ADDR' => self::PROXY,
                    'HTTP_X_FORWARDED_FOR' => self::ATTACKER,
                    'HTTP_X_REAL_IP' => self::ATTACKER,
                    'HTTP_CF_CONNECTING_IP' => self::SPOOFED,
                ],
                'expected' => self::ATTACKER,
            ],
        ];

        $ok = true;

        foreach ($scenarios as $scenario) {
            $resolved = $this->resolveThroughStack($scenario['server']);

            if ($resolved === $scenario['expected']) {
                $this->line("  <info>cadeia</info>: {$scenario['name']} → <options=bold>{$resolved}</>");

                continue;
            }

            $this->error("  cadeia: {$scenario['name']}");
            $this->error("          esperado {$scenario['expected']}, resolveu ".var_export($resolved, true));
            $ok = false;
        }

        return $ok;
    }

    /**
     * Passa uma requisição sintética pelos dois middlewares e devolve o que a
     * aplicação enxergaria como IP do cliente.
     *
     * @param  array<string, string>  $server
     */
    private function resolveThroughStack(array $server): ?string
    {
        $stack = array_values(array_filter(
            $this->globalMiddleware(),
            fn (string $middleware) => in_array($middleware, [TrustProxies::class, ResolveCloudflareClientIp::class], true)
        ));

        $request = Request::create('https://localhost/up', 'GET', [], [], [], $server);

        return (new Pipeline(app()))
            ->send($request)
            ->through($stack)
            ->then(fn (Request $resolved) => $resolved->ip());
    }

    /**
     * Compara as faixas versionadas com as publicadas pelo Cloudflare.
     */
    private function checkUpstreamRanges(): bool
    {
        $local = (array) config('trustedproxy.cloudflare', []);
        $published = [];

        foreach (['https://www.cloudflare.com/ips-v4', 'https://www.cloudflare.com/ips-v6'] as $url) {
            try {
                $response = Http::timeout(10)->get($url);
            } catch (\Throwable $e) {
                $this->error("  upstream: não foi possível ler {$url} — {$e->getMessage()}");

                return false;
            }

            if ($response->failed()) {
                $this->error("  upstream: {$url} respondeu {$response->status()}.");

                return false;
            }

            $published = array_merge($published, preg_split('/\R/', trim($response->body())) ?: []);
        }

        $published = array_values(array_filter(array_map('trim', $published)));

        if ($published === []) {
            $this->error('  upstream: o Cloudflare respondeu, mas sem nenhuma faixa — nada a comparar.');

            return false;
        }

        $missing = array_diff($published, $local);
        $extra = array_diff($local, $published);

        if ($missing === [] && $extra === []) {
            $this->line(sprintf('  <info>upstream</info>: as %d faixas versionadas são exatamente as publicadas', count($local)));

            return true;
        }

        $this->error('  upstream: a lista de config/trustedproxy.php divergiu da publicada pelo Cloudflare.');

        foreach ($missing as $range) {
            $this->line("            <options=bold>+</> {$range}  (publicada, falta no config)");
        }

        foreach ($extra as $range) {
            $this->line("            <options=bold>-</> {$range}  (no config, não é mais publicada)");
        }

        $this->newLine();
        $this->warn('  Faixa que falta devolve o IP da BORDA no lugar do cliente para quem entrar por ela.');
        $this->warn('  Atualize $cloudflare em config/trustedproxy.php e a data do comentário acima dele.');

        return false;
    }
}
