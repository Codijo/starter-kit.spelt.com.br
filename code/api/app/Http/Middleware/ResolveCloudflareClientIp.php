<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Recupera o IP do cliente quando o proxy do Coolify apaga o X-Forwarded-For
 * que o Cloudflare montou.
 *
 * ── Por que este middleware existe ────────────────────────────────────────
 *
 * O `TrustProxies` resolve a cadeia pelo X-Forwarded-For, e isso basta quando
 * a borda consegue repassar o header. Com a configuração PADRÃO do proxy do
 * Coolify, ela não consegue.
 *
 * O Traefik, em `pkg/middlewares/forwardedheaders`, faz isto na entrada:
 *
 *     if !x.insecure && !x.isTrustedIP(r.RemoteAddr) {
 *         DeleteXForwardedHeaders(r.Header)
 *     }
 *
 * e `isTrustedIP` devolve `false` sempre que `forwardedHeaders.trustedIPs`
 * está vazio — que é o default do Coolify (as flags geradas estão em
 * `bootstrap/helpers/proxy.php`; trustedIPs só entra como "custom command").
 *
 * Resultado: o X-Forwarded-For com o IP do cliente, montado pelo Cloudflare, é
 * APAGADO. O que chega ao container é um X-Forwarded-For com o endereço da
 * BORDA do Cloudflare — e `$request->ip()` devolveria um IP do Cloudflare como
 * se fosse o do cliente. Trocar um endereço errado (o do proxy) por outro
 * errado (o da borda) não é progresso.
 *
 * O `CF-Connecting-IP` sobrevive: não começa com `X-Forwarded-`, então não está
 * na lista que o Traefik apaga. É dele que o IP do cliente é recuperado aqui.
 *
 * ── Por que isso não é um buraco de segurança ─────────────────────────────
 *
 * Acreditar em `CF-Connecting-IP` sem mais nada seria: o header é texto que
 * qualquer cliente escreve, e quem alcançasse a origem por fora do Cloudflare
 * escolheria o IP que a aplicação registra — inclusive para escapar do rate
 * limit ou plantar o IP de um terceiro num registro de descadastro.
 *
 * A condição que fecha isso é a da linha de `arrivedFromCloudflareEdge()`: o
 * header só vale quando a cadeia JÁ resolvida termina na borda do Cloudflare.
 * Esse endereço não vem do cliente — vem do Traefik, que o escreve com o peer
 * real da conexão depois de apagar o que o cliente tentou mandar. Quem bate na
 * origem direto aparece com o próprio endereço ali, que não é faixa do
 * Cloudflare, e o `CF-Connecting-IP` que ele mandou é ignorado.
 *
 * ── O que acontece quando a infra é corrigida ─────────────────────────────
 *
 * Com `forwardedHeaders.trustedIPs` apontando para as faixas do Cloudflare no
 * proxy do Coolify, o Traefik passa a repassar o header e o `TrustProxies`
 * sozinho já chega ao cliente.
 *
 * Este middleware pode continuar disparando ali: o Traefik escreve o
 * `X-Real-Ip` com o endereço da borda em QUALQUER configuração, e ele é um dos
 * candidatos de `arrivedFromCloudflareEdge()`. Disparar não muda o resultado —
 * a reescrita PREPENDA o `CF-Connecting-IP` na cadeia em vez de substituí-la, e
 * nesse cenário o IP do cliente já estava à esquerda. Repetir o mesmo endereço
 * duas vezes deixa `$request->ip()` idêntico.
 *
 * É justamente por isso que a reescrita prepende: a operação tem que ser
 * inofensiva quando desnecessária, porque de dentro da aplicação não há como
 * saber qual das duas configurações o proxy está rodando hoje. E é o que faz
 * este middleware sobreviver ao dia em que o proxy do Coolify for recriado e
 * perder o custom command.
 */
class ResolveCloudflareClientIp
{
    /** Uma linha de log por janela, para não repetir o aviso a cada requisição. */
    private const WARN_TTL_SECONDS = 600;

    public function handle(Request $request, Closure $next)
    {
        if (! $this->arrivedFromCloudflareEdge($request)) {
            return $next($request);
        }

        $client = trim((string) $request->headers->get('CF-Connecting-IP'));

        if (filter_var($client, FILTER_VALIDATE_IP) === false) {
            // Chegou pela borda e o header não veio: não há de onde tirar o IP
            // do cliente. Avisa, porque o sintoma — todo IP registrado sendo um
            // endereço do Cloudflare — não aparece como erro em lugar nenhum.
            $this->warnOnce($request);

            return $next($request);
        }

        // Reconstrói a cadeia como o Cloudflare a tinha montado: o cliente à
        // esquerda, os intermediários à direita. O `TrustProxies` já rodou e
        // deixou as faixas confiáveis registradas; `getClientIps()` relê o
        // header a cada chamada, então a reescrita vale para toda a aplicação,
        // inclusive `$request->ip()` e a chave do `throttle`.
        $chain = trim((string) $request->headers->get('X-Forwarded-For'));

        $request->headers->set(
            'X-Forwarded-For',
            $chain === '' ? $client : $client.', '.$chain
        );

        return $next($request);
    }

    /**
     * A cadeia visível termina num endereço da borda do Cloudflare?
     *
     * Dois candidatos, porque os dois são escritos pelo Traefik com o peer real
     * da conexão — e não pelo cliente:
     *
     *   • `$request->ip()`, que aqui é o resultado do `TrustProxies`. Quando
     *     todos os saltos são confiáveis, o Symfony devolve o primeiro deles
     *     (`$firstTrustedIp`), que nesse caso é justamente a borda.
     *   • `X-Real-Ip`, que o Traefik escreve sempre, e que cobre o caso de o
     *     X-Forwarded-For não chegar de jeito nenhum — aí o `$request->ip()`
     *     cai no endereço do próprio proxy e não acusa o Cloudflare.
     */
    private function arrivedFromCloudflareEdge(Request $request): bool
    {
        $ranges = (array) config('trustedproxy.cloudflare', []);

        if ($ranges === []) {
            return false;
        }

        foreach ([$request->ip(), $request->headers->get('X-Real-Ip')] as $candidate) {
            $candidate = trim((string) $candidate);

            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            if (IpUtils::checkIp($candidate, $ranges)) {
                return true;
            }
        }

        return false;
    }

    private function warnOnce(Request $request): void
    {
        try {
            // `add()` só grava se a chave não existir — é o próprio lock.
            if (! Cache::add('proxies:cf-connecting-ip-missing', true, self::WARN_TTL_SECONDS)) {
                return;
            }
        } catch (\Throwable) {
            // Cache indisponível não pode derrubar a requisição por causa de um
            // aviso. Segue sem o lock: no pior caso, repete o log.
        }

        Log::warning('[Proxies] Requisição veio pela borda do Cloudflare sem CF-Connecting-IP: o IP do cliente não pôde ser recuperado e o IP registrado é o da borda.', [
            'resolved_ip' => $request->ip(),
            'x_real_ip' => $request->headers->get('X-Real-Ip'),
            'x_forwarded_for' => $request->headers->get('X-Forwarded-For'),
            'path' => $request->getPathInfo(),
        ]);
    }
}
