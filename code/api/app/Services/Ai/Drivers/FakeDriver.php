<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\DTO\AiRole;
use App\Services\Ai\DTO\AiUsage;

/**
 * Driver simulador — determinístico, sem rede. Para testes (Pest) e ambientes sem
 * chave configurada. Ecoa a última mensagem do usuário; respeita `json`.
 */
final class FakeDriver implements AiDriver
{
    public function __construct(
        private readonly array $driver = [],
        private readonly array $profile = [],
    ) {}

    public function chat(AiRequest $request): AiResponse
    {
        $lastUser = '';
        foreach ($request->messages as $message) {
            if ($message->role === AiRole::User) {
                $lastUser = $message->content;
            }
        }

        $model = (string) ($this->profile['model'] ?? 'fake/echo');

        $text = $request->json
            ? (string) json_encode(['echo' => $lastUser, 'model' => $model], JSON_UNESCAPED_UNICODE)
            : '[fake] '.$lastUser;

        $prompt = max(1, str_word_count($lastUser));

        return new AiResponse(
            text: $text,
            model: $model,
            usage: new AiUsage(promptTokens: $prompt, completionTokens: 1, totalTokens: $prompt + 1),
            finishReason: 'stop',
            raw: ['fake' => true],
        );
    }
}
