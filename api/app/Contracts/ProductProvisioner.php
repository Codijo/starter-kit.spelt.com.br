<?php

namespace App\Contracts;

use App\Models\Core\Account\Account;

/**
 * Interface ProductProvisioner
 *
 * Ponto de extensão do produto. O kit entrega os GANCHOS (chamados por fila a partir
 * dos eventos do Spelt); cada produto que copia o kit implementa esta interface e
 * troca o binding em AppServiceProvider. Produtos sem infra por conta usam o
 * App\Services\Spelt\NullProductProvisioner (padrão). Ver docs/technical/starter-kit.md §10.
 *
 * Mesma convenção de contrato do Spelt (App\Contracts\Storage\BucketInterface).
 */
interface ProductProvisioner
{
    /** Criar a infra da conta conforme os entitlements (ao ativar). */
    public function provision(Account $account): void;

    /**
     * Reagir a upgrade/downgrade. O produto decide a política (reconciliar forte,
     * soft-cap, etc.) — o kit NÃO desprovisiona sozinho.
     *
     * @param  array  $oldEntitlements  snapshot anterior
     * @param  array  $newEntitlements  snapshot novo
     */
    public function onPlanChanged(Account $account, array $oldEntitlements, array $newEntitlements): void;

    /** Liberar a infra da conta (ao cancelar/suspender). */
    public function teardown(Account $account): void;
}
