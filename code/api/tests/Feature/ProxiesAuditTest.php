<?php

/**
 * IP do cliente atrás do Cloudflare + proxy do Coolify (padrão do Spelt).
 *
 * O `proxies:audit` passa requisições sintéticas pelos middlewares REAIS do stack global, nos cinco
 * cenários que importam — inclusive o bypass do Cloudflare com CF-Connecting-IP forjado, que tem de
 * resolver para o IP do atacante. Se alguém tirar o `ResolveCloudflareClientIp` do bootstrap/app.php,
 * esvaziar a lista ou inverter a ordem, este teste falha antes do deploy.
 */
it('resolve o IP do cliente nos cinco cenários da cadeia de proxies', function () {
    $this->artisan('proxies:audit')
        ->expectsOutputToContain('bypass do Cloudflare com CF-Connecting-IP forjado → 198.51.100.9')
        ->assertSuccessful();
});
