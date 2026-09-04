<?php

namespace App\Jobs\Spelt;

use App\Enums\Status\Spelt\WebhookEventStatus;
use App\Models\Spelt\WebhookEvent;
use App\Services\Spelt\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Processa um webhook do Spelt de forma assíncrona (o controller responde 200 em < 5s).
 * Idempotente: se o evento já foi processado, sai sem efeito.
 */
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120, 300];

    public int $timeout = 30;

    public function __construct(public int $webhookEventId)
    {
        // Fila/connection SEMPRE pinadas no CONSTRUTOR — aprendizado do Spelt:
        // os métodos queue()/connection() NÃO são hooks do Laravel, então definir
        // por lá vira código morto e o job cai no default silenciosamente. A fila
        // precisa ser observada por um worker/supervisor (fallback: 'default').
        $this->onConnection('redis');
        $this->onQueue(env('QUEUE_WEBHOOK', 'default'));
    }

    public function handle(WebhookProcessor $processor): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if (! $event || $event->status === WebhookEventStatus::PROCESSED) {
            return;
        }

        $processor->handle($event->event, $event->payload['data'] ?? []);

        $event->update([
            'status' => WebhookEventStatus::PROCESSED,
            'processed_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        WebhookEvent::whereKey($this->webhookEventId)->update(['status' => WebhookEventStatus::FAILED]);
    }
}
