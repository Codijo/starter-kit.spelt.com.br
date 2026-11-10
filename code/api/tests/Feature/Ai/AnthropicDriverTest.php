<?php

use App\Models\Ai\AiUsageLog;
use App\Services\Ai\AiProvider;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiUsageContext;
use App\Services\Ai\Exceptions\AiException;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Driver da Anthropic direto (SDK oficial). Um Guzzle com MockHandler faz o papel da API, sem rede, e guarda os
 * pedidos para conferir cabeçalhos e corpo.
 */
function anthropicMessage(array $overrides = []): array
{
    return array_merge([
        'id' => 'msg_01',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => '{"ok":true}'],
        ],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 1000000, 'output_tokens' => 100000],
    ], $overrides);
}

/**
 * Configura o perfil `live` no driver anthropic, com as respostas dadas.
 *
 * @return ArrayObject os pedidos enviados
 */
function fakeAnthropic(array $responses, array $profile = []): ArrayObject
{
    $history = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler(array_map(
        fn ($r) => $r instanceof Response ? $r : new Response(200, ['Content-Type' => 'application/json'], json_encode($r)),
        $responses,
    )));
    $stack->push(Middleware::history($history));

    config()->set('ai.default_profile', 'live');
    config()->set('ai.profiles.live', array_merge(['driver' => 'anthropic', 'model' => 'claude-opus-5-5', 'temperature' => 0.7, 'max_tokens' => 8000], $profile));
    config()->set('ai.drivers.anthropic', ['api_key' => 'sk-ant-test', 'timeout' => 30, 'transporter' => new Guzzle(['handler' => $stack])]);
    config()->set('ai.pricing', ['claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00]]);

    return $history;
}

it('envia system à parte, sem temperature, com o esforço do perfil, e lê só o texto', function () {
    $history = fakeAnthropic([anthropicMessage()], ['effort' => 'low']);

    $res = app(AiProvider::class)->chat(AiRequest::make()->system('Você classifica.')->system('Seja breve.')->user('oi')->temperature(0.2)->asJson());

    expect($res->text)->toBe('{"ok":true}')
        ->and($res->json())->toBe(['ok' => true])
        ->and($res->model)->toBe('claude-opus-5-5')
        ->and($res->finishReason)->toBe('stop')
        ->and($res->usage->promptTokens)->toBe(1000000)
        ->and($res->usage->totalTokens)->toBe(1100000);

    $request = $history[0]['request'];
    $body = json_decode((string) $request->getBody(), true);

    expect((string) $request->getUri())->toEndWith('/v1/messages')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer sk-ant-test') // o SDK 1.x manda a chave como Bearer
        ->and($request->getHeaderLine('anthropic-version'))->not->toBe('')
        ->and($body['model'])->toBe('claude-opus-5-5')
        ->and($body['max_tokens'])->toBe(8000)
        ->and($body['system'])->toBe("Você classifica.\n\nSeja breve.\n\nResponda apenas com um objeto JSON válido, sem texto antes ou depois.")
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => 'oi']])
        ->and($body['output_config'])->toBe(['effort' => 'low'])
        ->and($body)->not->toHaveKey('temperature');
});

it('max_tokens vira "length", como no OpenRouter, para quem já confere o corte', function () {
    fakeAnthropic([anthropicMessage(['stop_reason' => 'max_tokens'])]);

    expect(app(AiProvider::class)->chat(AiRequest::make()->user('oi'))->finishReason)->toBe('length');
});

it('resposta só com raciocínio, cortada no max_tokens, diz que o raciocínio gastou o limite', function () {
    fakeAnthropic([anthropicMessage(['stop_reason' => 'max_tokens', 'content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig']]])]);

    expect(fn () => app(AiProvider::class)->chat(AiRequest::make()->user('oi')))
        ->toThrow(AiException::class, 'o raciocínio gastou o max_tokens');
});

it('recusa vira AiException com a categoria', function () {
    fakeAnthropic([anthropicMessage(['stop_reason' => 'refusal', 'content' => [], 'stop_details' => ['type' => 'refusal', 'category' => 'cyber', 'explanation' => null]])]);

    expect(fn () => app(AiProvider::class)->chat(AiRequest::make()->user('oi')))->toThrow(AiException::class, 'cyber');
});

it('chave inválida vira AiException com o status, sem tentar de novo', function () {
    $history = fakeAnthropic([new Response(401, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]))]);

    expect(fn () => app(AiProvider::class)->chat(AiRequest::make()->user('oi')))->toThrow(AiException::class, 'HTTP 401');
    expect($history)->toHaveCount(1);
});

it('sem chave, falha antes de chamar', function () {
    $history = fakeAnthropic([anthropicMessage()]);
    config()->set('ai.drivers.anthropic.api_key', '');

    expect(fn () => app(AiProvider::class)->chat(AiRequest::make()->user('oi')))->toThrow(AiException::class, 'ANTHROPIC_API_KEY');
    expect($history)->toHaveCount(0);
});

it('o log de uso grava o fornecedor e o custo pelo id da Anthropic', function () {
    fakeAnthropic([anthropicMessage()]);

    app(AiProvider::class)->chat(AiRequest::make()->user('oi'), 'live', new AiUsageContext(action: 'studio.generation'));

    $log = AiUsageLog::sole();
    expect($log->driver)->toBe('anthropic')
        ->and($log->provider)->toBe('anthropic')
        ->and($log->total_cost_usd)->toBe('6.00000000'); // 1M × $4 + 0,1M × $20
});
