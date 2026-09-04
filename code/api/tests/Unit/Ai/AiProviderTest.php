<?php

use App\Services\Ai\AiProvider;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\Drivers\FakeDriver;
use App\Services\Ai\Drivers\OpenRouterDriver;
use App\Services\Ai\Exceptions\AiException;

beforeEach(function () {
    config()->set('ai.default_profile', 'default');
    config()->set('ai.profiles.default', ['driver' => 'fake', 'model' => 'fake/default']);
    config()->set('ai.profiles.fast', ['driver' => 'fake', 'model' => 'fake/fast']);
    config()->set('ai.profiles.live', ['driver' => 'openrouter', 'model' => 'anthropic/x']);
    config()->set('ai.drivers.openrouter', ['api_key' => 'k', 'base_url' => 'https://openrouter.ai/api/v1']);
    config()->set('ai.drivers.fake', []);
});

it('resolve o driver do perfil default', function () {
    expect(app(AiProvider::class)->driver())->toBeInstanceOf(FakeDriver::class);
});

it('resolve o driver de um perfil nomeado', function () {
    expect(app(AiProvider::class)->driver('live'))->toBeInstanceOf(OpenRouterDriver::class);
});

it('o bind de conveniência AiDriver devolve o driver do perfil default', function () {
    expect(app(AiDriver::class))->toBeInstanceOf(FakeDriver::class);
});

it('perfil desconhecido lança AiException', function () {
    app(AiProvider::class)->driver('inexistente');
})->throws(AiException::class);

it('driver desconhecido no perfil lança AiException', function () {
    config()->set('ai.profiles.default.driver', 'zzz');
    app(AiProvider::class)->driver();
})->throws(AiException::class);

it('FakeDriver ecoa a última mensagem do usuário', function () {
    $res = app(AiProvider::class)->chat(AiRequest::make()->system('s')->user('olá'));

    expect($res->text)->toBe('[fake] olá');
    expect($res->model)->toBe('fake/default');
    expect($res->usage->totalTokens)->toBeGreaterThan(0);
});

it('FakeDriver devolve JSON válido quando json = true', function () {
    $res = app(AiProvider::class)->chat(AiRequest::make()->user('x')->asJson());

    expect($res->json())->toBe(['echo' => 'x', 'model' => 'fake/default']);
});

it('o modelo NÃO é escolhido na chamada — vem do perfil', function () {
    $default = app(AiProvider::class)->chat(AiRequest::make()->user('x'));
    $fast = app(AiProvider::class)->chat(AiRequest::make()->user('x'), 'fast');

    expect($default->model)->toBe('fake/default');
    expect($fast->model)->toBe('fake/fast');
});
