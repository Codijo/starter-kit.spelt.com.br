<?php

namespace App\Services\Ai\DTO;

/**
 * Pedido genérico de chat. NÃO carrega o modelo — o modelo vem do PERFIL, resolvido
 * pelo AiProvider. `temperature`/`maxTokens` são overrides opcionais do perfil.
 * `json = true` pede saída estruturada (response_format json).
 */
final class AiRequest
{
    /** @param AiMessage[] $messages */
    public function __construct(
        public array $messages = [],
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public bool $json = false,
    ) {}

    public static function make(array $messages = []): self
    {
        return new self($messages);
    }

    public function system(string $content): self
    {
        $this->messages[] = AiMessage::system($content);

        return $this;
    }

    public function user(string $content): self
    {
        $this->messages[] = AiMessage::user($content);

        return $this;
    }

    public function assistant(string $content): self
    {
        $this->messages[] = AiMessage::assistant($content);

        return $this;
    }

    public function temperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function maxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function asJson(bool $json = true): self
    {
        $this->json = $json;

        return $this;
    }

    /** @return array<int,array{role:string,content:string}> */
    public function messagesArray(): array
    {
        return array_map(fn (AiMessage $m) => $m->toArray(), $this->messages);
    }
}
