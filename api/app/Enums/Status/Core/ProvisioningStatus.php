<?php

namespace App\Enums\Status\Core;

/** Estado do provisionamento da infra do produto para a conta. */
enum ProvisioningStatus: string
{
    case PENDING = 'pending';
    case PROVISIONED = 'provisioned';
    case SUSPENDED = 'suspended';
    case TORN_DOWN = 'torn_down';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROVISIONED => 'Provisionada',
            self::SUSPENDED => 'Suspensa',
            self::TORN_DOWN => 'Removida',
        };
    }
}
