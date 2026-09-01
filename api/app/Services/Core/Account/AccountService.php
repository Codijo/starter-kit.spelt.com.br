<?php

namespace App\Services\Core\Account;

use App\Enums\Status\Core\AccountStatus;
use App\Enums\Status\Core\UserStatus;
use App\Models\Core\Account\Account;
use App\Models\Core\Account\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cria/atualiza Account e User a partir dos payloads do Spelt (webhook e SSO).
 * SSO-only: o usuário nasce com senha aleatória (nunca usada). Spec §8.1/§8.2.
 */
class AccountService
{
    /** Garante a conta existir (por `spelt_tenant_id`). */
    public function ensure(string $speltTenantId, ?string $name = null): Account
    {
        $account = Account::firstOrCreate(
            ['spelt_tenant_id' => $speltTenantId],
            ['name' => $name ?: 'Conta', 'status' => AccountStatus::ACTIVE],
        );

        // Enriquece o nome placeholder quando um nome real chega depois — ex.: a conta nasceu
        // de um `credit.granted` (que não traz nome) antes do `customer.created`/SSO.
        if ($name && ! $account->wasRecentlyCreated && in_array($account->name, [null, '', 'Conta'], true)) {
            $account->forceFill(['name' => $name])->save();
        }

        return $account;
    }

    /** Cria/atualiza a conta (e o usuário, se houver e-mail) a partir de um webhook. */
    public function upsertFromWebhook(array $data): Account
    {
        // CustomerDTO expõe o customer como `id`/`name`/`email`; DTOs de subscription/invoice/credit
        // trazem `customer_id`/`customer_name`/`customer_email`. Lê os dois, na ordem certa.
        $customerId = $data['customer_id'] ?? $data['id'];
        $name = $data['customer_name'] ?? $data['name'] ?? null;
        $email = $data['customer_email'] ?? $data['email'] ?? null;

        $account = $this->ensure($customerId, $name ?? $email);

        if (! empty($email)) {
            $this->upsertUser($account, $email, $name);
        }

        return $account;
    }

    /** Resolve conta + usuário a partir do payload validado de SSO. */
    public function upsertFromSso(array $customer): array
    {
        $account = $this->ensure($customer['tenant_id'], $customer['tenant_name'] ?? $customer['tenant_email'] ?? null);
        $user = $this->upsertUser($account, $customer['tenant_email'], $customer['tenant_name'] ?? null);

        // Aprende o portal Customer do Seller a partir do payload do SSO (`panel_url` = .../guard).
        // É o que dispensa a env SPELT_CUSTOMER_PORTAL_URL: o managePortal() usa este valor.
        $panelUrl = $customer['panel_url'] ?? null;
        if ($panelUrl && $account->spelt_panel_url !== $panelUrl) {
            $account->forceFill(['spelt_panel_url' => $panelUrl])->save();
        }

        return [$account, $user];
    }

    public function upsertUser(Account $account, string $email, ?string $name = null, ?string $speltUserId = null): User
    {
        $user = User::firstOrNew(['account_id' => $account->id, 'email' => $email]);

        if (! $user->exists) {
            $user->name = $name ?: $email;
            $user->password = Hash::make(Str::random(40)); // SSO-only — senha aleatória, não usada
            $user->status = UserStatus::ACTIVE;
        }

        if ($speltUserId) {
            $user->spelt_user_id = $speltUserId;
        }

        $user->save();

        return $user;
    }
}
