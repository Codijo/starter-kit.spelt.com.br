<?php

namespace App\Models\Billing;

use App\Enums\Type\Billing\CreditType;
use App\Models\Core\Account\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class CreditLedger
 *
 * Ledger append-only de créditos consumíveis. O SALDO vem do Spelt (CreditService::balance);
 * o ledger é o histórico, a reserva quando o Spelt não responde (SUM(amount)) e a fila do
 * consumo que ainda não foi contado lá (`spelt_reported_at` nulo).
 * Alimentado pelos webhooks de crédito do Spelt (`seller.credit.granted` +,
 * `seller.credit.expired` −) e pelo CONSUMO do produto (−, valor arbitrário por
 * operação — o Spelt só concede, o produto gasta). Spec §8.6.
 *
 * Responsabilidades:
 * - Registrar cada concessão/expiração/consumo como uma linha imutável
 *
 * Regras de negócio ficam nos Services:
 * - App\Services\Billing\CreditService — grant()/expire()/consume()/balance()
 *
 * @property int $id
 * @property string $account_id
 * @property CreditType $type
 * @property int $amount             + concessão · − expiração/consumo
 * @property string|null $spelt_credit_id  referência ao grant no Spelt
 * @property string|null $reference  chave de idempotência do consumo (product-side); único por (account_id, reference)
 * @property string|null $spelt_report_reference  envio ao Spelt que levou este consumo (gravada antes do envio)
 * @property \Carbon\Carbon|null $spelt_reported_at  quando o Spelt confirmou o consumo; nulo = ainda pendente
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CreditLedger extends Model
{
    protected $table = 'billing_credit_ledger';

    protected $fillable = [
        'account_id',       // FK: Account (Core\Account\Account)
        'type',             // CreditType: grant | expire | consume
        'amount',           // signed — + concede, − expira/consome
        'spelt_credit_id',  // referência ao grant do Spelt (quando aplicável)
        'reference',        // chave de idempotência do consumo — único por (account_id, reference)
        'spelt_report_reference', // envio ao Spelt que levou o consumo (CreditUsageReporter)
        'spelt_reported_at',      // confirmação do Spelt; nulo = consumo ainda não contado lá
        'meta',             // JSON livre
    ];

    protected function casts(): array
    {
        return [
            'type' => CreditType::class,
            'amount' => 'integer',
            'meta' => 'array',
            'spelt_reported_at' => 'datetime',
        ];
    }

    // === RELACIONAMENTOS ===

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
