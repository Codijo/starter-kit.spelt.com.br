<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jobs pinam onConnection('redis') no construtor (padrão do Spelt). Em teste,
        // forçamos a conexão redis a rodar via driver 'sync' para o job executar inline
        // — senão o pin sobrescreveria o QUEUE_CONNECTION=sync do phpunit.xml.
        config(['queue.connections.redis.driver' => 'sync']);
    }
}
