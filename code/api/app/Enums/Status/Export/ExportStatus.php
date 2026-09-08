<?php

namespace App\Enums\Status\Export;

/**
 * Onde o pedido de exportação está.
 *
 * `Processando` existe separado de `Na fila` porque a diferença importa para quem espera: na
 * fila significa "ainda não começou", processando significa "está lendo a base agora". Sem
 * essa distinção, uma fila cheia parece uma exportação travada.
 */
enum ExportStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Na fila',
            self::PROCESSING => 'Gerando',
            self::COMPLETED => 'Pronto',
            self::FAILED => 'Falhou',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PROCESSING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
        };
    }

    /** Ainda vai mudar sozinho — é o que faz a tela continuar perguntando. */
    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ], self::cases());
    }
}
