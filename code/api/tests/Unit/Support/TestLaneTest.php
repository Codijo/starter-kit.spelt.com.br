<?php

use Tests\Support\TestLane;

/*
|--------------------------------------------------------------------------
| A lane da execução e a trava que impede duas no mesmo banco
|--------------------------------------------------------------------------
|
| Duas execuções no mesmo banco se derrubam: o `migrate:fresh` de uma apaga as
| tabelas sob os pés da outra, e a reprovação cai em testes sem relação com o que
| estava sendo mexido.
|
| O que estes casos defendem é a REGRA DE NOME e o CRITÉRIO DO COLETOR, que são
| funções puras e não precisam de app, banco nem trava para ser exercitados.
|
| ⚠️ **A catraca do `TestLane::apply()` estar ligado não está aqui** — está em
| `tests/Feature/Support/TestLaneLockTest.php`. Em `Unit` nenhuma trava é adquirida.
|
*/

it('insere a lane antes do sufixo de teste', function () {
    expect(TestLane::databaseFor('app_testing', 'b'))
        ->toBe('app_b_testing');

    expect(TestLane::databaseFor('app_testing', 'x2'))
        ->toBe('app_x2_testing');
});

it('deixa o nome intacto quando não há lane', function () {
    expect(TestLane::databaseFor('app_testing', ''))
        ->toBe('app_testing');
});

it('não apelida banco que está fora do padrão de teste', function () {
    // `app` é o banco de DEV, que é cópia de produção. Renomeá-lo
    // esconderia o alvo da `TestingDatabaseGuard`, que reprova justamente por ele não
    // terminar em `_testing`.
    expect(TestLane::databaseFor('app', 'b'))->toBe('app');
    expect(TestLane::databaseFor('', 'b'))->toBe('');
});

it('reconhece o banco de uma lane a partir do nome padrão', function () {
    expect(TestLane::laneOf('app_b_testing', 'app_testing'))->toBe('b');
    expect(TestLane::laneOf('app_fin2_testing', 'app_testing'))->toBe('fin2');
});

it('nunca toma o banco padrão nem o de outro projeto por lane', function () {
    // O padrão é o banco que o `phpunit.xml` declara: o coletor nunca o apaga.
    expect(TestLane::laneOf('app_testing', 'app_testing'))->toBeNull();

    // O `infra-mysql` é compartilhado: banco de teste de outro projeto não casa.
    expect(TestLane::laneOf('outro_projeto_b_testing', 'app_testing'))->toBeNull();
    expect(TestLane::laneOf('outro_projeto_testing', 'app_testing'))->toBeNull();

    // Banco de DEV (cópia de produção) não termina em `_testing` e não casa nunca.
    expect(TestLane::laneOf('app_b', 'app_testing'))->toBeNull();
    expect(TestLane::laneOf('app_b_testing', 'app'))->toBeNull();

    // Lane só tem letra e dígito: `b_c` seria outro nome-base, não uma lane.
    expect(TestLane::laneOf('app_b_c_testing', 'app_testing'))->toBeNull();
});

it('aceita auto como pedido de sorteio', function () {
    $previous = getenv('TEST_LANE');
    putenv('TEST_LANE=AUTO');

    try {
        expect(TestLane::requested())->toBe(TestLane::AUTO);
    } finally {
        $previous === false ? putenv('TEST_LANE') : putenv("TEST_LANE={$previous}");
    }
});

it('recusa lane que não serve como nome de banco', function (string $lane) {
    $previous = getenv('TEST_LANE');
    putenv("TEST_LANE={$lane}");

    try {
        expect(fn () => TestLane::current())
            ->toThrow(RuntimeException::class, 'TEST_LANE inválida');
    } finally {
        $previous === false ? putenv('TEST_LANE') : putenv("TEST_LANE={$previous}");
    }
})->with([
    'aspas e ponto-e-vírgula' => ['b`; DROP DATABASE x; --'],
    'espaço' => ['minha lane'],
    'longa demais' => ['abcdefghi'],
    'traço' => ['lane-b'],
]);
