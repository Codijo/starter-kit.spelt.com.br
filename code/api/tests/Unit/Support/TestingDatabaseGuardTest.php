<?php

use Tests\Support\TestingDatabaseGuard;

/*
|--------------------------------------------------------------------------
| A guarda que impede a suíte de apagar o banco de dev
|--------------------------------------------------------------------------
|
| O banco de dev costuma ter dado de verdade e `RefreshDatabase` roda `migrate:fresh`. Esta
| guarda é o que fica entre um `config:cache` esquecido e a perda dessa cópia, então
| ela própria é testada: o modo de falhar dela é passar quando deveria barrar.
|
| Testar a guarda de verdade (apontando a suíte para o banco de dev) seria apostar a
| cópia de dado real na aposta. Por isso ela é uma classe de uma função, exercitada
| aqui com config de mentira.
*/

it('aceita quando toda conexão termina em _testing', function () {
    TestingDatabaseGuard::assertAllTargetTesting([
        'mysql' => ['driver' => 'mysql', 'database' => 'app_testing'],
        'mysql_send' => ['driver' => 'mysql', 'database' => 'app_send_testing'],
        'mongodb' => ['driver' => 'mongodb', 'database' => 'app_contact_testing'],
    ]);

    expect(true)->toBeTrue(); // chegar aqui sem exceção é a asserção
});

it('barra a conexão que aponta para banco de dev, e diz qual', function () {
    expect(fn () => TestingDatabaseGuard::assertAllTargetTesting([
        'mysql' => ['driver' => 'mysql', 'database' => 'app_testing'],
        'mysql_send' => ['driver' => 'mysql', 'database' => 'app_send'],
    ]))->toThrow(RuntimeException::class, 'mysql_send');
});

it('barra database vazio, porque aí quem decide o alvo é o DSN', function () {
    expect(fn () => TestingDatabaseGuard::assertAllTargetTesting([
        'mongodb_metric' => ['driver' => 'mongodb', 'dsn' => 'mongodb://mongo:27017', 'database' => null],
    ]))->toThrow(RuntimeException::class, '(vazio)');
});

it('não depende de lista de drivers: conexão nova entra coberta', function () {
    expect(fn () => TestingDatabaseGuard::assertAllTargetTesting([
        'pgsql_novo' => ['driver' => 'pgsql', 'database' => 'alguma_coisa'],
    ]))->toThrow(RuntimeException::class, 'pgsql_novo');
});

it('aceita o :memory: do SQLite, que não persiste nada', function () {
    TestingDatabaseGuard::assertAllTargetTesting([
        'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ]);

    expect(true)->toBeTrue();
});

it('aceita o alvo desligado de propósito, unreachable_*', function () {
    // Conexão que existe na config mas que nenhum teste deve alcançar (uma importação, um
    // banco legado remoto): nome que não existe em servidor nenhum, e que a lane não toca.
    TestingDatabaseGuard::assertAllTargetTesting([
        'legacy' => ['driver' => 'mysql', 'database' => 'unreachable_legacy'],
    ]);

    expect(true)->toBeTrue();
});

it('não aceita unreachable_ fora do começo do nome, nem o sufixo curto _test', function (string $database) {
    expect(fn () => TestingDatabaseGuard::assertAllTargetTesting([
        'mysql' => ['driver' => 'mysql', 'database' => $database],
    ]))->toThrow(RuntimeException::class, 'Banco de teste inválido');
})->with([
    'unreachable no meio' => ['app_unreachable_x'],
    'sufixo curto' => ['app_test'],
]);
