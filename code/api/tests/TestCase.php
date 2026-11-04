<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jobs pinam onConnection('redis') no construtor (padrão do Spelt). Em teste,
        // forçamos a conexão redis a rodar via driver 'sync' para o job executar inline
        // — senão o pin sobrescreveria o QUEUE_CONNECTION=sync do phpunit.xml.
        config(['queue.connections.redis.driver' => 'sync']);

        // Nenhum teste sai para a rede. O saldo de crédito vem do Spelt (CreditService::balance):
        // sem isto, todo teste que lê o saldo perguntava a `api.spelt.test` e só passava porque
        // o DNS falhava. Requisição não fingida estoura, e o saldo cai no ledger local.
        Http::preventStrayRequests();
    }
}
