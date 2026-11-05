<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestingDatabaseGuard;
use Tests\Support\TestLane;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot do app em teste — a lane desta execução e a guarda dos bancos.
     *
     * As duas rodam aqui porque este é o último ponto antes de qualquer trait de
     * banco: `setUpTheTestEnvironment()` chama `createApplication()` antes de
     * `setUpTraits()`, onde o `RefreshDatabase` entra.
     *
     * A ordem importa. `TestLane` pode REESCREVER o alvo das conexões (é o que a
     * `TEST_LANE` faz), então a guarda confere o nome final.
     *
     * O banco de teste vem de `tests/bootstrap.php` (MySQL, `<app>_testing`): a guarda
     * recusa qualquer conexão fora de `_testing`, e a lane (`TEST_LANE=auto`) dá a cada
     * execução simultânea o seu `<app>_<lane>_testing`.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        TestLane::apply($app, $this->rebuildsDatabase());

        TestingDatabaseGuard::assertAllTargetTesting(
            (array) $app['config']->get('database.connections', [])
        );

        return $app;
    }

    /**
     * Este caso reconstrói o banco?
     *
     * Só quem usa `RefreshDatabase` roda `migrate:fresh`, e só quem roda
     * `migrate:fresh` disputa o banco com outra execução.
     */
    private function rebuildsDatabase(): bool
    {
        return in_array(RefreshDatabase::class, class_uses_recursive(static::class), true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Jobs pinam onConnection('redis') no construtor (padrão do Spelt). Em teste,
        // forçamos a conexão redis a rodar via driver 'sync' para o job executar inline
        // — senão o pin sobrescreveria o QUEUE_CONNECTION=sync do phpunit.xml.
        config(['queue.connections.redis.driver' => 'sync']);

        // Nenhum teste sai para a rede. O saldo de crédito vem do Spelt (CreditService::balance):
        // sem isto, todo teste que entrega Lead perguntava a `api.spelt.test` e só passava porque
        // o DNS falhava. Requisição não fingida estoura, e o saldo cai no ledger local.
        Http::preventStrayRequests();
    }
}
