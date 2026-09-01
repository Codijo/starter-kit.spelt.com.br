<?php

use App\Services\Ai\AiProvider;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('ai.default_profile', 'live');
    config()->set('ai.profiles.live', [
        'driver' => 'openrouter', 'model' => 'anthropic/claude-3.5-sonnet',
        'temperature' => 0.7, 'max_tokens' => 100,
    ]);
    config()->set('ai.drivers.openrouter', [
        'api_key' => 'test-key', 'base_url' => 'https://openrouter.ai/api/v1',
        'referer' => 'https://meu.app', 'title' => 'Meu App', 'timeout' => 30,
    ]);
});

it('monta o payload OpenAI-compatible e faz parse da resposta', function () {
    Http::fake(['openrouter.ai/*' => Http::response([
        'model' => 'anthropic/claude-3.5-sonnet',
        'choices' => [['message' => ['content' => 'Olá!'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3, 'total_tokens' => 15],
    ])]);

    $res = app(AiProvider::class)->chat(AiRequest::make()->system('s')->user('oi'));

    expect($res->text)->toBe('Olá!');
    expect($res->model)->toBe('anthropic/claude-3.5-sonnet');
    expect($res->usage->totalTokens)->toBe(15);
    expect($res->finishReason)->toBe('stop');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request['model'] === 'anthropic/claude-3.5-sonnet'
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1]['content'] === 'oi'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request->hasHeader('HTTP-Referer', 'https://meu.app')
            && $request->hasHeader('X-Title', 'Meu App');
    });
});

it('json = true envia response_format json_object', function () {
    Http::fake(['openrouter.ai/*' => Http::response([
        'choices' => [['message' => ['content' => '{"a":1}']]],
        'usage' => [],
    ])]);

    $res = app(AiProvider::class)->chat(AiRequest::make()->user('x')->asJson());

    expect($res->json())->toBe(['a' => 1]);
    Http::assertSent(fn ($r) => ($r['response_format']['type'] ?? null) === 'json_object');
});

it('override de temperature/maxTokens na chamada vence o perfil', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => []])]);

    app(AiProvider::class)->chat(AiRequest::make()->user('x')->temperature(0.1)->maxTokens(7));

    Http::assertSent(fn ($r) => $r['temperature'] === 0.1 && $r['max_tokens'] === 7);
});

it('HTTP != 2xx lança AiException', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => 'bad'], 400)]);

    app(AiProvider::class)->chat(AiRequest::make()->user('x'));
})->throws(AiException::class);

it('sem api_key lança AiException', function () {
    config()->set('ai.drivers.openrouter.api_key', '');

    app(AiProvider::class)->chat(AiRequest::make()->user('x'));
})->throws(AiException::class);
