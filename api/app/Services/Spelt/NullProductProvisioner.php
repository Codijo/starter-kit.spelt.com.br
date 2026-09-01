<?php

namespace App\Services\Spelt;

use App\Contracts\ProductProvisioner;
use App\Models\Core\Account\Account;
use Illuminate\Support\Facades\Log;

/**
 * Provisioner padrão: no-op. Serve produtos sem infra por conta ("só CRUD").
 * Um produto com infra própria implementa App\Contracts\ProductProvisioner e
 * substitui o binding em AppServiceProvider.
 */
class NullProductProvisioner implements ProductProvisioner
{
    public function provision(Account $account): void
    {
        Log::debug('[Spelt] provision no-op', ['account' => $account->id]);
    }

    public function onPlanChanged(Account $account, array $oldEntitlements, array $newEntitlements): void
    {
        Log::debug('[Spelt] onPlanChanged no-op', ['account' => $account->id]);
    }

    public function teardown(Account $account): void
    {
        Log::debug('[Spelt] teardown no-op', ['account' => $account->id]);
    }
}
