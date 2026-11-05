<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Recusa subir a suíte quando alguma conexão aponta para banco que não é de teste.
 *
 * ⚠️ O banco de desenvolvimento costuma ter dado de verdade — uma cópia de
 * produção, o trabalho do dia —, e
 * `RefreshDatabase` executa `migrate:fresh`: derruba e recria todas as tabelas da
 * conexão default. Basta a sobrescrita do phpunit.xml não chegar à conexão — config
 * em cache de outra execução, `--configuration` apontando para outro arquivo,
 * variável exportada no ambiente do container vencendo a do XML — para o alvo virar o
 * banco de dev. Não há desfazer.
 *
 * Confere TODAS as conexões, não só a default: conexão nova entra no
 * `config/database.php` sem ninguém lembrar da suíte — e qualquer uma é escrita por
 * teste que exercite o módulo dela, sem limpeza depois.
 *
 * Sem lista de drivers de propósito: driver novo entra coberto. O que passa é nome
 * terminado em `_testing`, o `:memory:` do SQLite, que não persiste nada, e o alvo
 * desligado `unreachable_*`. `database` vazio reprova — aí quem decide o alvo é o
 * DSN, que esta guarda não lê.
 */
final class TestingDatabaseGuard
{
    private const TESTING_SUFFIX = '_testing';

    private const IN_MEMORY = ':memory:';

    /**
     * Prefixo de alvo desligado de propósito: conexão que existe na config mas que
     * nenhum teste deve alcançar (uma importação, um banco legado remoto).
     * Nome que não existe em servidor nenhum — quem tentar escrever falha na conexão —,
     * e que a `TestLane` não renomeia, não cria nem apaga.
     */
    private const UNREACHABLE_PREFIX = 'unreachable_';

    /**
     * @param  array<string, mixed>  $connections  `database.connections` da config
     *
     * @throws RuntimeException na primeira conexão fora do padrão
     */
    public static function assertAllTargetTesting(array $connections): void
    {
        foreach ($connections as $name => $connection) {
            $database = is_array($connection) ? (string) ($connection['database'] ?? '') : '';

            if ($database === self::IN_MEMORY
                || str_ends_with($database, self::TESTING_SUFFIX)
                || str_starts_with($database, self::UNREACHABLE_PREFIX)) {
                continue;
            }

            throw new RuntimeException(
                "Banco de teste inválido: a conexão `{$name}` aponta para "
                .($database === '' ? '(vazio)' : "`{$database}`")
                .', que não termina em `'.self::TESTING_SUFFIX.'`. A suíte escreve nesse banco, '
                .'e o de dev é cópia de produção. Sobrescreva o database dessa conexão no '
                .'phpunit.xml — `<nome>'.self::TESTING_SUFFIX.'` se a suíte usa, `'
                .self::UNREACHABLE_PREFIX.'<nome>` se nenhum teste deve alcançá-la — e rode '
                .'`php artisan config:clear`.'
            );
        }
    }
}
