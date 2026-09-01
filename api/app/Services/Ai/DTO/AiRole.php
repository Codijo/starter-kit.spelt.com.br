<?php

namespace App\Services\Ai\DTO;

/** Papel de uma mensagem no chat (padrão OpenAI/Anthropic). */
enum AiRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
}
