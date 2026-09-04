<?php

namespace App\Services\Ai\DTO;

/** Resposta genérica de chat. `raw` guarda o payload cru do fornecedor. */
final class AiResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly AiUsage $usage,
        public readonly ?string $finishReason = null,
        public readonly array $raw = [],
    ) {}

    /** Decodifica `text` como JSON (para chamadas feitas com json = true). */
    public function json(bool $associative = true): mixed
    {
        return json_decode($this->text, $associative);
    }
}
