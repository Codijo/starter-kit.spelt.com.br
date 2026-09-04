<?php

namespace App\Traits\Concerns;

/**
 * Trait Scope
 *
 * Tenancy leve do kit. A "conta" (Account) é o Spelt Tenant espelhado localmente;
 * o isolamento é por `account_id` — o mesmo vetor de vazamento que o `tenant_id`
 * no Spelt. Use `forCurrentAccount()` em TODA query de dado do produto.
 */
trait Scope
{
    /** Filtra pelo `account_id` do usuário autenticado. */
    public function scopeForCurrentAccount($query)
    {
        return $query->where('account_id', request()->user()->account_id);
    }

    /** Filtra por owner polimórfico (instância). */
    public function scopeForOwner($query, $owner)
    {
        return $query->where([
            'owner_type' => get_class($owner),
            'owner_id' => $owner->getKey(),
        ]);
    }
}
