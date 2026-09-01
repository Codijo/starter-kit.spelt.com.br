<?php

namespace App\Services\Ai\DTO;

/** Consumo de tokens de uma chamada — útil p/ medir custo / ledger de créditos. */
final class AiUsage
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $totalTokens = 0,
    ) {}

    /** Constrói a partir do bloco `usage` no formato OpenAI/OpenRouter. */
    public static function fromArray(?array $usage): self
    {
        $usage ??= [];

        return new self(
            promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
            completionTokens: (int) ($usage['completion_tokens'] ?? 0),
            totalTokens: (int) ($usage['total_tokens'] ?? 0),
        );
    }
}
