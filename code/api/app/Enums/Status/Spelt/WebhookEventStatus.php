<?php

namespace App\Enums\Status\Spelt;

/** Estado de processamento de um webhook recebido do Spelt. */
enum WebhookEventStatus: string
{
    case RECEIVED = 'received';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Recebido',
            self::PROCESSED => 'Processado',
            self::FAILED => 'Falhou',
        };
    }
}
