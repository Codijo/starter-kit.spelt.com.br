<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Cookie do token da API — mesma mecânica que FUNCIONA no Customer do Spelt
 * (`splt_customer_token`): 30 dias, host-only (`domain=null`, sobrevive a domínio
 * white-label), `secure`/`httpOnly`/`sameSite` variando por ambiente. Server-side lê e
 * injeta no `window.App` (o front usa Bearer direto na API). Renomeie no seu produto.
 */
class TokenCookie
{
    public const NAME = 'kit_token';

    public static function make(string $token): SymfonyCookie
    {
        $isLocal = app()->isLocal();

        return cookie(
            self::NAME,
            $token,
            60 * 24 * 30,          // 30 dias (min)
            '/',
            null,                  // host-only (não fixar domínio)
            ! $isLocal,            // secure: DEV false, PRD true
            ! $isLocal,            // httpOnly: DEV false, PRD true
            false,
            $isLocal ? 'lax' : 'none', // cross-site no redirect de SSO em PRD
        );
    }

    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(self::NAME);
    }
}
