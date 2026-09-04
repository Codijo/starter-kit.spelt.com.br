<?php

namespace App\Traits\Core\Account;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait Authorization
 *
 * Usada nos Controllers. Injeta `account_id`/`user_id` no payload antes do create e
 * garante posse da conta antes de update/delete. Contraparte de escrita do
 * App\Traits\Concerns\Scope (leitura).
 */
trait Authorization
{
    protected function currentAccountId(): string
    {
        return request()->user()->account_id;
    }

    protected function injectAccountId(array $data): array
    {
        return array_merge($data, ['account_id' => $this->currentAccountId()]);
    }

    protected function injectUserId(array $data): array
    {
        return array_merge($data, ['user_id' => request()->user()->getKey()]);
    }

    /** Aborta 403 se o model não pertencer à conta autenticada. */
    protected function ensureAccountOwnership(Model $model): void
    {
        if ($model->account_id !== $this->currentAccountId()) {
            abort(403, 'Acesso negado.');
        }
    }
}
