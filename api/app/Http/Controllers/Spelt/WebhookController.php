<?php

namespace App\Http\Controllers\Spelt;

use App\Enums\Status\Spelt\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Spelt\ProcessWebhookJob;
use App\Models\Spelt\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe webhooks do Spelt. Autenticado pelo middleware `spelt.webhook` (Bearer secret).
 * Responde 200 em < 5s: apenas grava (idempotente por `spelt_event_id`) e enfileira.
 * Ver spec §8.2.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventId = $payload['id'] ?? null;
        $eventName = $payload['event'] ?? null;

        if (! $eventId || ! $eventName) {
            return $this->fail('Payload de webhook inválido.', 422);
        }

        // Idempotência: só cria a linha se o evento ainda não foi recebido.
        $event = WebhookEvent::firstOrCreate(
            ['spelt_event_id' => $eventId],
            [
                'event' => $eventName,
                'payload' => $payload,
                'received_at' => now(),
                'status' => WebhookEventStatus::RECEIVED,
            ],
        );

        // Novo evento → processa. Reentrega de um evento que FALHOU (esgotou as tentativas) →
        // reprocessa: os handlers são idempotentes, então é uma recuperação segura de operador.
        // Um evento já PROCESSED continua sendo no-op (a rede de segurança real é `spelt:reconcile`).
        if ($event->wasRecentlyCreated) {
            ProcessWebhookJob::dispatch($event->id);
        } elseif ($event->status === WebhookEventStatus::FAILED) {
            $event->update(['status' => WebhookEventStatus::RECEIVED]);
            ProcessWebhookJob::dispatch($event->id);
        }

        return response()->json(['received' => true]);
    }
}
