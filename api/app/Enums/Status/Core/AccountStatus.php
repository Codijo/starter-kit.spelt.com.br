<?php

namespace App\Enums\Status\Core;

/** Status da conta (= espelho do Tenant no Spelt). */
enum AccountStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativa',
            self::SUSPENDED => 'Suspensa',
            self::CANCELLED => 'Cancelada',
        };
    }
}
