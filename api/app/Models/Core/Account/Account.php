<?php

namespace App\Models\Core\Account;

use App\Enums\Status\Core\AccountStatus;
use App\Enums\Status\Core\ProvisioningStatus;
use App\Models\Billing\CreditLedger;
use App\Models\Billing\Subscription;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Account
 *
 * Identidade da conta no produto — o espelho local de um Tenant (customer) do Spelt.
 * É a raiz da tenancy leve do kit: todo dado do produto pertence a uma Account, e o
 * isolamento se dá por `account_id` (ver App\Traits\Concerns\Scope::forCurrentAccount).
 *
 * Responsabilidades:
 * - Manter a ponte com o Spelt (`spelt_tenant_id`, único)
 * - Guardar o status da conta e do provisionamento da infra do produto
 * - Expor, de forma read-only, o gate de acesso, os entitlements e o saldo de créditos
 *   — todos derivados do estado sincronizado do Spelt (Billing\Subscription + Billing\CreditLedger)
 *
 * Regras de negócio ficam nos Services:
 * - App\Services\Spelt\*            — sincronização a partir dos webhooks/SSO do Spelt
 * - App\Services\Billing\CreditService — débito/crédito no ledger
 * - App\Contracts\ProductProvisioner   — provisão/teardown da infra do produto
 *
 * @property string $id
 * @property string $spelt_tenant_id
 * @property string $name
 * @property AccountStatus $status
 * @property ProvisioningStatus $provisioning_status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Account extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'core_accounts';

    protected $fillable = [
        // === Ponte com o Spelt ===
        'spelt_tenant_id',      // FK lógica: o Tenant (customer) no Spelt — único
        'spelt_panel_url',      // Portal Customer do Seller, aprendido no SSO (dispensa env)

        // === Identificação ===
        'name',                 // Nome da conta (vem do cadastro no Spelt)

        // === Status ===
        'status',               // AccountStatus (espelha o Tenant no Spelt)
        'provisioning_status',  // ProvisioningStatus (infra do produto)
    ];

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'provisioning_status' => ProvisioningStatus::class,
        ];
    }

    // === RELACIONAMENTOS ===

    /** Usuários (time) da conta. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Estado de billing + entitlements sincronizados do Spelt (1:1 nesta versão). */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /** Ledger append-only de créditos (saldo = SUM(amount)). */
    public function creditLedger(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }

    // === BUSINESS LOGIC - ESTADO (consultas simples) ===

    /** Fonte de verdade do gate: assinatura ativa E com acesso liberado. */
    public function hasAccess(): bool
    {
        return $this->subscription?->hasAccess() ?? false;
    }

    /** Valor de um entitlement de capacidade (limite/flag) do plano. Ver spec §9. */
    public function entitlement(string $key, mixed $default = null): mixed
    {
        return data_get($this->subscription?->entitlements, $key, $default);
    }

    /** Flag booleana de entitlement (ex.: custom_domain). */
    public function allows(string $key): bool
    {
        return (bool) $this->entitlement($key, false);
    }

    /** true se `$current` ainda cabe no limite do entitlement `$key` (null = sem limite). */
    public function withinLimit(string $key, int $current): bool
    {
        $limit = $this->entitlement($key);

        return $limit === null ? true : $current < (int) $limit;
    }

    /** Saldo de créditos consumíveis (ledger append-only). Ver spec §8.6. */
    public function creditBalance(): int
    {
        return (int) $this->creditLedger()->sum('amount');
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::ACTIVE;
    }
}
