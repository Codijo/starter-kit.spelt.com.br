<?php

namespace App\Models\Ai;

use App\Models\Core\Account\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class AiUsageLog
 *
 * Registro append-only de uso de IA (LLM): um por chamada, com tokens e custo em USD, o
 * "quem" (account/user) e o "para quê" (action + assunto polimórfico). Base de auditoria de
 * custo por projeto/tenant/feature. Padrão do kit — todo projeto que usa IA herda.
 *
 * Responsabilidades:
 * - Guardar tokens de entrada/saída e o custo calculado no momento da chamada (snapshot).
 * - Amarrar o uso a um tenant/usuário e ao objeto de domínio que o disparou.
 *
 * Regras de negócio ficam no Service:
 * - App\Services\Ai\AiUsageLogger: calcula custo (config `ai.pricing`) e grava (à prova de falha).
 *
 * @property int $id
 * @property string|null $account_id
 * @property string|null $user_id
 * @property string $driver     transporte (openrouter | anthropic | fake)
 * @property string $provider   fornecedor subjacente (anthropic | openai …)
 * @property string $model
 * @property string $action     rótulo semântico (ex.: studio.generation)
 * @property string|null $context_type
 * @property string|null $context_id
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string|null $cost_input_usd
 * @property string|null $cost_output_usd
 * @property string|null $total_cost_usd
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AiUsageLog extends Model
{
    protected $table = 'ai_usage_logs';

    protected $fillable = [
        // === Quem ===
        'account_id',       // FK: Tenant que originou o uso (nullable — chamadas de sistema)
        'user_id',          // FK: usuário que disparou (nullable)

        // === O quê / qual modelo ===
        'driver',           // transporte: openrouter | anthropic | fake
        'provider',         // fornecedor subjacente: anthropic | openai …
        'model',            // modelo efetivo retornado
        'action',           // rótulo semântico: studio.generation

        // === Assunto polimórfico ===
        'context_type',     // ex.: App\Models\Studio\Generation
        'context_id',

        // === Consumo / custo ===
        'input_tokens',
        'output_tokens',
        'cost_input_usd',   // snapshot (config ai.pricing no momento)
        'cost_output_usd',
        'total_cost_usd',

        'metadata',         // JSON livre
    ];

    protected function casts(): array
    {
        return [
            'input_tokens'    => 'integer',
            'output_tokens'   => 'integer',
            'cost_input_usd'  => 'decimal:8',
            'cost_output_usd' => 'decimal:8',
            'total_cost_usd'  => 'decimal:8',
            'metadata'        => 'array',
        ];
    }

    // === RELACIONAMENTOS ===

    /** Tenant que originou o uso. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Objeto de domínio que gerou o uso (ex.: Generation). */
    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
