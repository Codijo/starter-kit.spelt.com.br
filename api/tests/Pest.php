<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Helper: POST autenticado no webhook do Spelt (secret de teste). */
function postWebhook(array $payload)
{
    return test()->withHeaders(['Authorization' => 'Bearer test-secret'])
        ->postJson('/api/spelt/webhook', $payload);
}
