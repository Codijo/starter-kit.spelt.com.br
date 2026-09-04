<?php

namespace App\Models\Core\Account;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Class PersonalAccessToken
 *
 * Substitui o modelo padrão do Sanctum (registrado em AppServiceProvider via
 * Sanctum::usePersonalAccessTokenModel) para capturar IP e User-Agent na emissão
 * do token — alimenta a gestão de "Sessões ativas" sem tocar cada call-site de
 * createToken(). Mesmo padrão do Spelt.
 *
 * Responsabilidades:
 * - Persistir `ip_address` e `user_agent` a partir da request corrente
 * - Garantir um `expires_at` próprio (piso) já que a janela global do Sanctum é nula
 *
 * @property int $id
 * @property string $name
 * @property array|null $abilities
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon|null $expires_at
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'ip_address',
        'user_agent',
    ];

    protected static function boot()
    {
        parent::boot();

        // Preenche metadados do dispositivo + garante expiração própria. Call-sites que
        // passam expires_at explícito (SSO curto, etc.) ficam intactos (o === null pula).
        static::creating(function (self $token) {
            $request = request();

            if ($token->ip_address === null) {
                $token->ip_address = $request?->ip();
            }

            if ($token->user_agent === null) {
                $ua = $request?->userAgent();
                $token->user_agent = $ua ? mb_substr($ua, 0, 1024) : null;
            }

            if ($token->expires_at === null) {
                $token->expires_at = now()->addDays((int) config('services.spelt.session_ttl_days', 30));
            }
        });
    }
}
