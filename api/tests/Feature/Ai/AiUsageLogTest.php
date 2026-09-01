<?php

use App\Models\Ai\AiUsageLog;
use App\Models\Core\Account\Account;
use App\Services\Ai\AiProvider;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiUsageContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('ai.profiles.live', ['driver' => 'openrouter', 'model' => 'anthropic/claude-sonnet-4', 'temperature' => 0.9, 'max_tokens' => 100]);
    config()->set('ai.drivers.openrouter', ['api_key' => 'k', 'base_url' => 'https://openrouter.ai/api/v1']);
    config()->set('ai.pricing', ['anthropic/claude-sonnet-4' => ['input' => 3.00, 'output' => 15.00]]);
});

it('grava um ai_usage_log com tokens, custo e contexto', function () {
    Http::fake(['openrouter.ai/*' => Http::response([
        'model' => 'anthropic/claude-sonnet-4',
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 4239, 'completion_tokens' => 326, 'total_tokens' => 4565],
    ])]);
    $account = Account::factory()->create();

    app(AiProvider::class)->chat(
        AiRequest::make()->user('x'),
        'live',
        new AiUsageContext(
            action: 'studio.generation',
            accountId: $account->id,
            subjectType: 'App\\Models\\Studio\\Generation',
            subjectId: '42',
            metadata: ['k' => 'v'],
        ),
    );

    $log = AiUsageLog::firstOrFail();
    expect($log->account_id)->toBe($account->id);
    expect($log->action)->toBe('studio.generation');
    expect($log->driver)->toBe('openrouter');
    expect($log->provider)->toBe('anthropic');
    expect($log->model)->toBe('anthropic/claude-sonnet-4');
    expect($log->input_tokens)->toBe(4239);
    expect($log->output_tokens)->toBe(326);
    expect($log->cost_input_usd)->toBe('0.01271700');   // 4239 / 1M * $3
    expect($log->cost_output_usd)->toBe('0.00489000');  // 326 / 1M * $15
    expect($log->total_cost_usd)->toBe('0.01760700');
    expect($log->context_type)->toBe('App\\Models\\Studio\\Generation');
    expect($log->context_id)->toBe('42');
    expect($log->metadata)->toBe(['k' => 'v']);
});

it('não grava log quando não há usage context', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => []])]);

    app(AiProvider::class)->chat(AiRequest::make()->user('x'), 'live'); // sem usage

    expect(AiUsageLog::count())->toBe(0);
});

it('modelo sem preço → custo nulo, tokens preservados', function () {
    config()->set('ai.profiles.live.model', 'zzz/unknown');
    config()->set('ai.pricing', []);
    Http::fake(['openrouter.ai/*' => Http::response([
        'model' => 'zzz/unknown',
        'choices' => [['message' => ['content' => 'ok']]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ])]);

    app(AiProvider::class)->chat(AiRequest::make()->user('x'), 'live', new AiUsageContext(action: 'x'));

    $log = AiUsageLog::firstOrFail();
    expect($log->input_tokens)->toBe(10);
    expect($log->cost_input_usd)->toBeNull();
    expect($log->total_cost_usd)->toBeNull();
});

it('falha ao gravar o log NÃO quebra a chamada de IA', function () {
    Schema::drop('ai_usage_logs'); // simula falha de persistência
    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => []])]);

    $res = app(AiProvider::class)->chat(AiRequest::make()->user('x'), 'live', new AiUsageContext(action: 'x'));

    expect($res->text)->toBe('ok'); // resposta veio apesar do log ter falhado
});
