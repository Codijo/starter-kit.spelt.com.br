<?php

namespace App\Models\Spelt;

use App\Enums\Status\Spelt\WebhookEventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WebhookEvent
 *
 * Ledger de idempotência + auditoria dos webhooks recebidos do Spelt. O
 * `spelt_event_id` é único: retries (o Spelt reenvia em falha) são detectados e
 * ignorados. Spec §8.2.
 *
 * Responsabilidades:
 * - Deduplicar eventos por `spelt_event_id`
 * - Guardar o payload bruto e o resultado do processamento (auditoria)
 *
 * Regras de negócio ficam nos Services:
 * - App\Services\Spelt\WebhookProcessor — roteia o evento para a ação de domínio
 *
 * Retenção: 90 dias (`model:prune`, agendado em routes/console.php). O payload traz nome e e-mail do
 * cliente, e um ex-cliente não pode ficar aqui para sempre. A janela de dedup não sofre: o Spelt
 * desiste de reenviar muito antes disso.
 *
 * @property int $id
 * @property string $spelt_event_id
 * @property string $event
 * @property array $payload
 * @property \Carbon\Carbon $received_at
 * @property \Carbon\Carbon|null $processed_at
 * @property WebhookEventStatus $status
 */
class WebhookEvent extends Model
{
    use MassPrunable;

    /** Dias que o evento recebido fica guardado. */
    public const RETENTION_DAYS = 90;

    protected $table = 'spelt_webhook_events';

    protected $fillable = [
        'spelt_event_id',   // dedup — o `id` do evento no Spelt
        'event',            // ex.: seller.subscription.activated
        'payload',          // envelope completo do webhook
        'received_at',
        'processed_at',
        'status',           // WebhookEventStatus
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'status' => WebhookEventStatus::class,
        ];
    }

    /** O que o `model:prune` apaga: pela criação, qualquer que seja o status. */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
