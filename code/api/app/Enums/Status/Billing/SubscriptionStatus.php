<?php

namespace App\Enums\Status\Billing;

/** Status da assinatura (espelho do Spelt, sincronizado por webhook). */
enum SubscriptionStatus: string
{
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::INACTIVE => 'Inativa',
            self::ACTIVE => 'Ativa',
            self::PAST_DUE => 'Em atraso',
            self::CANCELLED => 'Cancelada',
        };
    }
}
