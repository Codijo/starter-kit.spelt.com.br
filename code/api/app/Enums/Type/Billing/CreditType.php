<?php

namespace App\Enums\Type\Billing;

/** Tipo de lançamento no ledger de créditos. */
enum CreditType: string
{
    case GRANT = 'grant';
    case EXPIRE = 'expire';
    case CONSUME = 'consume';

    public function label(): string
    {
        return match ($this) {
            self::GRANT => 'Concessão',
            self::EXPIRE => 'Expiração',
            self::CONSUME => 'Consumo',
        };
    }
}
