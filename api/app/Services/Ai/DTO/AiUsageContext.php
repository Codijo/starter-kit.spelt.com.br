<?php

namespace App\Services\Ai\DTO;

use Illuminate\Database\Eloquent\Model;

/**
 * Contexto de auditoria de uma chamada de IA — o "quem" e o "para quê". Passado a
 * `AiProvider::chat($request, $profile, $usage)`; o `AiUsageLogger` grava tokens/custo
 * (que vêm da resposta) junto com estes campos em `ai_usage_logs`.
 */
final class AiUsageContext
{
    public function __construct(
        /** Rótulo semântico da chamada, ex.: "studio.generation". */
        public readonly string $action,
        /** Tenant que originou o uso. */
        public readonly ?string $accountId = null,
        /** Usuário que disparou (auditoria mais fina). */
        public readonly ?string $userId = null,
        /** Assunto polimórfico (ex.: a Generation que gerou os tokens). */
        public readonly ?string $subjectType = null,
        public readonly ?string $subjectId = null,
        public readonly ?array $metadata = null,
    ) {}

    /** Atalho quando se tem o Model do assunto. */
    public static function forSubject(
        string $action,
        Model $subject,
        ?string $accountId = null,
        ?string $userId = null,
        ?array $metadata = null,
    ): self {
        return new self(
            action: $action,
            accountId: $accountId,
            userId: $userId,
            subjectType: $subject->getMorphClass(),
            subjectId: (string) $subject->getKey(),
            metadata: $metadata,
        );
    }
}
