<?php

namespace App\Models\Core\Account;

use App\Enums\Status\Core\UserStatus;
use App\Traits\Concerns\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class User
 *
 * Usuário do produto, sempre vinculado a uma Account (= Tenant do Spelt espelhado).
 * Nasce do handoff do Spelt (SSO ou webhook `customer.created`); o kit é SSO-only e
 * NÃO gere senha — a coluna existe por compatibilidade com o Authenticatable e é
 * preenchida com valor aleatório na provisão. Ver docs/technical/starter-kit.md §12.
 *
 * Responsabilidades:
 * - Autenticar via token Sanctum (guard `api`)
 * - Manter a ponte com o User do Spelt (`spelt_user_id`)
 * - Servir de âncora do isolamento (`account_id` → forCurrentAccount)
 *
 * Regras de negócio ficam nos Services:
 * - App\Services\Core\Account\AccountService — cria/atualiza o usuário a partir do Spelt
 *
 * @property string $id
 * @property string $account_id
 * @property string|null $spelt_user_id
 * @property string $name
 * @property string $email
 * @property string|null $avatar_url
 * @property UserStatus $status
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon|null $last_login_at
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlids;
    use Notifiable;
    use Scope;

    protected $table = 'core_account_users';

    protected $fillable = [
        // === Relacionamentos ===
        'account_id',       // FK: Account dona — `forCurrentAccount()` opera aqui
        'spelt_user_id',    // Ponte com o User do Spelt (nullable)

        // === Identificação ===
        'name',
        'email',            // único por conta (unique account_id+email)
        'avatar_url',

        // === Status / acesso ===
        'status',           // UserStatus
        'password',         // não gerido (SSO-only) — random na provisão
        'email_verified_at',
        'last_login_at',

        // === Livre ===
        'metadata',         // JSON livre por usuário
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'metadata' => 'array',
        ];
    }

    // === RELACIONAMENTOS ===

    /** Conta (Tenant do Spelt) à qual o usuário pertence. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // === BUSINESS LOGIC - ESTADO (consultas simples) ===

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
