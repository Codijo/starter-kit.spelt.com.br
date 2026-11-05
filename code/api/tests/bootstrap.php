<?php

require __DIR__.'/../vendor/autoload.php';

/**
 * Sobe a suíte contra o MySQL, num banco irmão do da aplicação com sufixo `_testing`.
 *
 * ── Por que não SQLite ────────────────────────────────────────────────────
 *
 * Porque ele mente sobre o que importa. O SQLite recusa derrubar coluna indexada sem antes
 * derrubar o índice, e o MySQL aceita: uma migration que passa num quebra no outro. Ele
 * também trata JSON como texto e diverge na semântica de índice único com NULL, que é onde
 * costuma morar a garantia de "não duplicar". Testar no banco que roda em produção é o que
 * faz a suíte dizer a verdade.
 *
 * ── Por que o nome é derivado, não escrito ────────────────────────────────
 *
 * Nada de nome fixo neste arquivo: cada produto que nasce do kit tem o seu banco. O sufixo
 * `_testing` sai do que estiver no `.env`, e o usuário de lá precisa de CREATE no servidor.
 *
 * `_testing`, e não `_test`, porque é o que a `TestingDatabaseGuard` e a `TestLane`
 * (`tests/Support/`) exigem: a lane entra antes dele (`<app>_b_testing`).
 */
$root = dirname(__DIR__);

$env = Dotenv\Dotenv::createArrayBacked($root)->safeLoad();

$read = fn (string $key, string $default = ''): string => (string) ($env[$key] ?? $_SERVER[$key] ?? $default);

$application = $read('DB_DATABASE');

if ($application === '') {
    fwrite(STDERR, "\n  DB_DATABASE não está definido no .env — a suíte não sabe qual banco espelhar.\n\n");
    exit(1);
}

$database = str_ends_with($application, '_testing') ? $application : $application.'_testing';

// Cinto de segurança: a suíte roda `RefreshDatabase`, que ESVAZIA o banco em que estiver.
// Apontar para o banco de desenvolvimento por descuido apagaria o trabalho do dia.
if (! str_ends_with($database, '_testing')) {
    fwrite(STDERR, "\n  Recusando rodar: o banco de teste precisa terminar em _testing.\n\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s', $read('DB_HOST', '127.0.0.1'), $read('DB_PORT', '3306')),
        $read('DB_USERNAME'),
        $read('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Sem a mensagem, o erro chega como "connection refused" no meio do primeiro teste e
    // parece bug de código.
    fwrite(STDERR, "\n  Não foi possível preparar o banco de teste `{$database}`.\n".
        "  O usuário do .env precisa de CREATE nesse servidor.\n\n  {$e->getMessage()}\n\n");
    exit(1);
}

// Antes de a aplicação subir: é daqui que o Laravel lê a conexão.
foreach (['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
