<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

/**
 * Dá a cada execução da suíte um conjunto próprio de bancos — e recusa duas
 * execuções no mesmo conjunto.
 *
 * ⚠️ **Duas suítes no mesmo banco se destroem, e o estrago não se parece com a
 * causa.** `RefreshDatabase` roda `migrate:fresh` no começo da execução: ele
 * DERRUBA todas as tabelas e recarrega o schema. Quando a segunda execução faz isso
 * com a primeira no meio do caminho, a primeira passa a reprovar em bloco com
 * `Base table or view not found`, `Deadlock found` ou `Table definition has
 * changed`, numa distribuição diferente a cada vez — o retrato de um teste instável.
 *
 * Com mais de uma sessão no mesmo repositório — pessoas, agentes, os dois —, isso
 * vira rotina: uma suíte derruba a da outra, e a reação errada ("rodar de novo
 * resolve") vira hábito, porque a causa não aparece no erro.
 *
 * Quatro peças, e todas são necessárias:
 *
 * 1. **Lane** — `TEST_LANE=b composer test` insere `_b` antes do `_testing` de
 *    TODAS as conexões, então duas sessões rodam ao mesmo tempo sem se tocar.
 *    `TEST_LANE=auto` pega a primeira lane livre do pool — é o que agente usa.
 * 2. **Trava** — um `flock` por lane. Quem chegar na lane ocupada para na hora,
 *    com a saída no texto do erro, em vez de descobrir quatro minutos depois por
 *    um teste que não tem nada a ver.
 * 3. **Descarte** — o banco de uma lane é apagado no fim da execução que o usou.
 *    Ele não vale nada entre uma execução e outra: o `migrate:fresh` do começo o
 *    reconstrói do zero de qualquer forma.
 * 4. **Coletor** — no começo de toda execução, apaga as lanes que ficaram para
 *    trás de uma execução que morreu sem chegar ao descarte (`kill -9`, OOM, VM
 *    caindo).
 *
 * ⚠️ As peças 3 e 4 nasceram de um vazamento medido: sem elas, todo nome de lane
 * inventado virava banco permanente — dezenas de bancos MySQL e Mongo em duas
 * semanas. A lane padrão (sem `TEST_LANE`) nunca é apagada: é o banco que o
 * `phpunit.xml` declara.
 *
 * O mesmo código roda em vários projetos — só os comentários mudam —, inclusive o
 * suporte a Mongo, inerte onde não há conexão Mongo. Banco padrão sem nome de teste
 * (o `:memory:` do SQLite) deixa a classe inteira inerte. Ao copiar, confira que o
 * código bate: `php -w tests/Support/TestLane.php | md5sum` igual nas cópias.
 *
 * A trava é `flock` e não linha em tabela de propósito: quem a segura é o
 * processo, e o sistema operacional a devolve quando ele morre — inclusive no
 * `kill -9` e no Ctrl+C, que é como uma execução de suíte costuma terminar. Linha
 * em tabela sobreviveria ao processo e travaria a lane para sempre.
 *
 * @see \Tests\Support\TestingDatabaseGuard  a guarda que confere o alvo depois daqui
 */
final class TestLane
{
    /** Sufixo que a `TestingDatabaseGuard` exige — a lane entra ANTES dele. */
    private const TESTING_SUFFIX = '_testing';

    /** Valor de `TEST_LANE` que pede a primeira lane livre do pool. */
    public const AUTO = 'auto';

    /**
     * As lanes que o `auto` distribui — e o teto de suítes simultâneas.
     *
     * O tamanho é escolha: cada suíte cheia pesa no ambiente de desenvolvimento, e o pool faz as
     * vezes de semáforo. O quarto agente espera em vez de derrubar os outros três.
     */
    public const POOL = ['b', 'c', 'd'];

    /**
     * Idade mínima para o coletor apagar uma lane cuja trava está livre.
     *
     * A trava livre já diz que ninguém NESTE diretório usa a lane. A idade cobre o
     * resto: uma execução que aponte para o mesmo servidor de outro lugar. A
     * suíte leva minutos, e o `migrate:fresh` renova o `create_time` a cada uma.
     */
    private const ORPHAN_AFTER_SECONDS = 2 * 3600;

    /**
     * O handle do `flock` vive aqui pelo processo inteiro.
     *
     * ⚠️ Não é cache: é o que SEGURA a trava. `flock` morre com o handle, e um
     * handle local seria fechado pelo coletor de lixo ao sair do método — a trava
     * cairia no mesmo instante em que foi adquirida, sem erro nenhum.
     *
     * @var resource|null
     */
    private static $lockHandle = null;

    /** A lane que o `auto` sorteou para este processo. Null enquanto não houve sorteio. */
    private static ?string $autoLane = null;

    /** O banco da conexão padrão, como o `phpunit.xml` o declara — a chave da trava. */
    private static ?string $mainDatabase = null;

    /** Coletor e criação de bancos já feitos neste processo. */
    private static bool $databasesReady = false;

    /** O descarte do fim já foi registrado neste processo. */
    private static bool $cleanupRegistered = false;

    /**
     * Aplica a lane às conexões e trava o conjunto para esta execução.
     *
     * Roda a cada `createApplication()` — ou seja, a cada teste —, porque a config
     * nasce de novo junto com a app. O que é caro (sortear, travar, coletar, criar
     * banco) acontece uma vez só, guardado pelas estáticas acima.
     *
     * ⚠️ **Nada aqui pode lançar.** Exceção neste ponto não interrompe a execução:
     * vira erro DAQUELE caso, e o PHPUnit segue para o próximo e repete o mesmo erro
     * uma vez por teste — o despejo ilegível que esta classe existe para substituir por
     * uma frase. Toda falha de preparo sai por `abort()`, que escreve uma vez e
     * encerra.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  bool  $touchesDatabase  o caso em questão reconstrói o banco?
     */
    public static function apply($app, bool $touchesDatabase = true): void
    {
        try {
            $requested = self::requested();
        } catch (RuntimeException $e) {
            self::abort($e->getMessage());
        }

        // Lido antes de qualquer renomeação: a config nasce de novo a cada app, ainda
        // com o nome do `phpunit.xml`.
        self::$mainDatabase ??= self::defaultDatabase($app);

        // Banco padrão sem nome de teste — o `:memory:` do SQLite — não disputa
        // nada: cada processo tem o seu. Sem esta saída, as três lanes do pool cairiam
        // na mesma trava (`:memory:` não tem `_testing` onde a lane entre) e a segunda
        // execução simultânea seria recusada sem motivo.
        if (! self::isLaneable()) {
            return;
        }

        // O sorteio só acontece quando alguém precisa de lane: um caso que toca banco,
        // ou um `current()` chamado por quem separa outro recurso por lane. Um `--filter`
        // de Unit com `auto` não ocupa lane nenhuma — e não é recusado com o pool cheio.
        if ($requested === self::AUTO && self::$autoLane === null && ! $touchesDatabase) {
            return;
        }

        $lane = self::current();

        if ($lane !== '') {
            self::renameConnections($app, $lane);
        }

        // Quem não reconstrói o banco não disputa nada: travar execução sem banco
        // impediria rodar um `--filter` de teste Unit enquanto a suíte cheia roda,
        // que é uso legítimo e não colide com ninguém.
        if (! $touchesDatabase) {
            return;
        }

        self::acquire(self::defaultDatabase($app));

        // ⚠️ Sem gate de lane. `CREATE DATABASE IF NOT EXISTS` é idempotente, e o gate
        // não comprava nada: deixava a lane PADRÃO de fora, então container recém
        // construído morria com "Unknown database `<app>_testing`" — a falha
        // exata que este método existe para evitar — enquanto `TEST_LANE=b` funcionava.
        if (! self::$databasesReady) {
            self::reapAbandoned($app);

            try {
                self::createMissingDatabases($app);
            } catch (RuntimeException $e) {
                self::abort($e->getMessage());
            }

            self::$databasesReady = true;
        }

        if ($lane !== '' && ! self::$cleanupRegistered) {
            self::registerCleanup($app, $lane);
            self::$cleanupRegistered = true;
        }
    }

    /**
     * A lane em uso, normalizada. String vazia quando não há nenhuma.
     *
     * Com `auto`, sorteia na primeira chamada. ⚠️ É de propósito que um getter faça
     * isso: quem separa por lane um recurso que não é banco — uma pasta IMAP, um
     * nome de fila, num teste Unit — chama `current()` antes de qualquer caso tocar
     * banco. Devolver vazio ali poria duas execuções `auto` na mesma pasta, sem erro
     * nenhum.
     */
    public static function current(): string
    {
        $requested = self::requested();

        if ($requested !== self::AUTO) {
            return $requested;
        }

        // Fora do `apply()`, ou com banco padrão que não é de lane, não há o que chavear.
        if (self::$autoLane === null && self::isLaneable()) {
            self::$autoLane = self::claimFromPool(self::$mainDatabase);
        }

        return self::$autoLane ?? '';
    }

    /**
     * O valor de `TEST_LANE` como foi pedido — `auto` incluído.
     *
     * O formato é restrito a letra e dígito porque o nome entra num `CREATE
     * DATABASE`, onde não há bind de parâmetro que o proteja.
     */
    public static function requested(): string
    {
        $lane = strtolower(trim(self::env('TEST_LANE')));

        if ($lane === '') {
            return '';
        }

        if (preg_match('/^[a-z0-9]{1,8}$/', $lane) !== 1) {
            throw new RuntimeException(
                "TEST_LANE inválida: `{$lane}`. Use de 1 a 8 letras ou dígitos — o valor "
                .'vira nome de banco, e nome de banco não aceita bind de parâmetro.'
            );
        }

        return $lane;
    }

    /**
     * O nome que um banco de teste assume numa lane. Função pura — é a regra, e é
     * por onde ela é testada.
     *
     * Lane vazia, ou nome que não termina em `_testing`, devolvem o nome original.
     */
    public static function databaseFor(string $database, string $lane): string
    {
        if ($lane === '' || ! str_ends_with($database, self::TESTING_SUFFIX)) {
            return $database;
        }

        $base = substr($database, 0, -strlen(self::TESTING_SUFFIX));

        return $base.'_'.$lane.self::TESTING_SUFFIX;
    }

    /**
     * A lane de que `$database` é banco, a partir do nome padrão `$base`. Null
     * quando não é banco de lane nenhuma desse nome. Função pura — é o critério do
     * coletor, e é por onde ele é testado.
     *
     * ⚠️ O nome padrão NUNCA casa: ele não tem lane, e é o banco que o `phpunit.xml`
     * declara. E banco de outro projeto no mesmo servidor só casa se começar
     * pelo mesmo nome-base — o que o coletor ainda confere contra as outras conexões.
     */
    public static function laneOf(string $database, string $base): ?string
    {
        if (! str_ends_with($base, self::TESTING_SUFFIX)) {
            return null;
        }

        $prefix = preg_quote(substr($base, 0, -strlen(self::TESTING_SUFFIX)), '/');

        if (preg_match('/^'.$prefix.'_([a-z0-9]{1,8})'.self::TESTING_SUFFIX.'$/', $database, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * Onde mora a trava de um banco.
     *
     * ⚠️ Dentro do projeto, e não no `/tmp`. O mesmo código está montado em mais de
     * um container (o do PHP e o da api, por exemplo), e cada um tem o seu `/tmp`:
     * com a trava lá, duas execuções em containers diferentes não se enxergavam e
     * pegavam a mesma lane. O diretório do projeto é o mesmo inode para os dois, e
     * o `flock` vale entre eles. `storage/framework/testing/` já é ignorado no git.
     */
    public static function lockPath(string $database): string
    {
        return dirname(__DIR__, 2).'/storage/framework/testing/lanes/'.$database.'.lock';
    }

    /** O banco da conexão padrão, como está na config agora. */
    private static function defaultDatabase($app): string
    {
        $default = (string) $app['config']->get('database.default');

        return (string) $app['config']->get("database.connections.{$default}.database");
    }

    /** O banco padrão admite lane? Só nome que termina em `_testing` — e só depois do `apply()`. */
    private static function isLaneable(): bool
    {
        return self::$mainDatabase !== null && str_ends_with(self::$mainDatabase, self::TESTING_SUFFIX);
    }

    /**
     * Sorteia a primeira lane livre do pool e já a deixa travada.
     *
     * Sondar e travar são o mesmo gesto de propósito: sondar primeiro e travar
     * depois deixaria duas execuções simultâneas verem a mesma lane livre.
     */
    private static function claimFromPool(string $database): string
    {
        foreach (self::POOL as $lane) {
            $handle = self::openLock(self::lockPath(self::databaseFor($database, $lane)));

            if ($handle === false) {
                continue;
            }

            if (flock($handle, LOCK_EX | LOCK_NB)) {
                self::hold($handle);

                return $lane;
            }

            fclose($handle);
        }

        self::abort(
            'As lanes do pool ('.implode(', ', self::POOL).") estão todas em uso.\n\n"
            ."O pool é o teto de suítes simultâneas: cada uma pesa no ambiente de desenvolvimento.\n"
            ."Espere uma terminar e rode de novo:  TEST_LANE=auto composer test\n"
        );
    }

    /**
     * Insere a lane antes do `_testing` de cada conexão.
     *
     * `app_testing` + lane `b` → `app_b_testing`.
     *
     * Nome que NÃO termina em `_testing` fica como está: quem decide se ele pode
     * ser escrito é a `TestingDatabaseGuard`, logo em seguida, e ela reprova. Dar
     * um apelido de lane a um banco fora do padrão só esconderia o alvo dela.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    private static function renameConnections($app, string $lane): void
    {
        $connections = (array) $app['config']->get('database.connections', []);

        foreach ($connections as $name => $connection) {
            $database = is_array($connection) ? (string) ($connection['database'] ?? '') : '';
            $renamed = self::databaseFor($database, $lane);

            if ($renamed !== $database) {
                $app['config']->set("database.connections.{$name}.database", $renamed);
            }
        }
    }

    /**
     * Trava a lane para este processo, ou explica quem a está usando.
     *
     * `LOCK_NB` porque esperar seria o pior dos dois mundos: a suíte cheia leva uns
     * quatro minutos, e quem esbarrasse ficaria olhando um terminal parado sem
     * saber por quê. Recusar na hora, com a saída escrita no erro, é o que
     * transforma a colisão em algo acionável.
     */
    private static function acquire(string $database): void
    {
        if (self::$lockHandle !== null) {
            return;
        }

        $path = self::lockPath($database);

        $handle = self::openLock($path);

        if ($handle === false) {
            // Sem poder travar, seguir é melhor que parar: o pior caso volta a ser o
            // de antes desta classe existir, e derrubar a suíte por causa de uma trava
            // trocaria um problema intermitente por um permanente.
            //
            // ⚠️ Mas seguir CALADO seria pior que os dois. A doc afirma que a colisão
            // não é mais silenciosa, e sem este aviso a afirmação fica falsa justo
            // quando importa.
            fwrite(
                STDERR,
                PHP_EOL."⚠️  Não foi possível abrir a trava da lane em `{$path}` — a suíte segue "
                .'SEM proteção contra outra execução no mesmo banco.'.PHP_EOL
                .'   Apague o arquivo, ou rode com o mesmo usuário da execução anterior.'.PHP_EOL
            );

            return;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            self::abort(self::busyMessage($path, $database));
        }

        self::hold($handle);
    }

    /** Guarda o handle travado e registra quem o segura. */
    private static function hold($handle): void
    {
        ftruncate($handle, 0);
        fwrite($handle, json_encode(['pid' => getmypid(), 'started_at' => time()]));
        fflush($handle);

        self::$lockHandle = $handle;
    }

    /**
     * Apaga os bancos da lane quando este processo terminar.
     *
     * Roda com a trava ainda segura — o `flock` só cai quando o processo libera os
     * recursos, DEPOIS das funções de shutdown —, então nenhum `auto` pega a lane
     * no meio do `DROP`.
     *
     * `TEST_LANE_KEEP=1` deixa os bancos para inspeção. Vale até o coletor de uma
     * execução seguinte passar por eles, `ORPHAN_AFTER_SECONDS` depois.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    private static function registerCleanup($app, string $lane): void
    {
        if (in_array(strtolower(self::env('TEST_LANE_KEEP')), ['1', 'true', 'yes'], true)) {
            return;
        }

        // A config, como está agora: já com os nomes da lane. Na hora do shutdown a
        // app do último teste já foi desmontada.
        $connections = (array) $app['config']->get('database.connections', []);

        register_shutdown_function(static function () use ($connections, $lane): void {
            self::releaseOpenConnections();
            self::dropLane($connections, $lane);
        });

        self::trapInterrupts();
    }

    /**
     * Faz o Ctrl+C e o `kill` comum passarem pelo shutdown.
     *
     * Sem isto, SIGINT e SIGTERM encerram o PHP sem rodar função de shutdown — e
     * Ctrl+C é como uma execução de suíte costuma terminar. `exit()` rodado de
     * dentro do handler passa pelo shutdown normalmente. `kill -9` não tem jeito:
     * é dele que o coletor cuida.
     */
    private static function trapInterrupts(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT => 130, SIGTERM => 143] as $signal => $code) {
            // Quem já trata o sinal continua tratando: não é aqui que se decide isso.
            if (pcntl_signal_get_handler($signal) !== SIG_DFL) {
                continue;
            }

            pcntl_signal($signal, static function () use ($code): void {
                exit($code);
            });
        }
    }

    /**
     * Fecha as conexões que a app de teste ainda tenha abertas.
     *
     * ⚠️ Interrompida no meio de um caso, a execução tem uma transação aberta do
     * `RefreshDatabase`, e ela segura metadata lock nas tabelas. O `DROP DATABASE`
     * de outra conexão do MESMO processo esperaria por esse lock, e quem o soltaria
     * é o processo que está esperando: a suíte travaria até o `lock_wait_timeout`.
     * Fechar a conexão desfaz a transação no servidor e solta o lock.
     */
    private static function releaseOpenConnections(): void
    {
        try {
            $container = \Illuminate\Container\Container::getInstance();

            if ($container->bound('db')) {
                foreach ($container->make('db')->getConnections() as $connection) {
                    $connection->disconnect();
                }
            }
        } catch (\Throwable) {
            // Melhor esforço: o `lock_wait_timeout` curto do `dropLane()` cobre o resto.
        }
    }

    /**
     * Apaga os bancos de uma lane — MySQL e Mongo.
     *
     * Só toca nome que termina em `_<lane>_testing`: a lane padrão e banco fora do
     * padrão de teste não chegam a ser candidatos. Falha vira aviso, nunca erro — o
     * pior caso é o banco ficar para o coletor.
     *
     * @param  array<string, mixed>  $connections  conexões já com o nome da lane
     */
    private static function dropLane(array $connections, string $lane): void
    {
        $suffix = '_'.$lane.self::TESTING_SUFFIX;

        foreach ($connections as $name => $connection) {
            $database = is_array($connection) ? (string) ($connection['database'] ?? '') : '';

            if ($lane === '' || ! str_ends_with($database, $suffix)) {
                continue;
            }

            try {
                match ($connection['driver'] ?? '') {
                    'mysql', 'mariadb' => self::pdoFor($connection)->exec("DROP DATABASE IF EXISTS `{$database}`"),
                    'mongodb' => self::mongoFor($connection)?->dropDatabase($database),
                    default => null,
                };
            } catch (\Throwable $e) {
                fwrite(STDERR, PHP_EOL."⚠️  Não foi possível apagar `{$database}` (conexão `{$name}`): {$e->getMessage()}".PHP_EOL);
            }
        }
    }

    /**
     * Apaga as lanes abandonadas por execuções que morreram antes do descarte.
     *
     * Uma lane é abandonada quando as três coisas valem ao mesmo tempo:
     * - o nome é `<nome padrão>_<lane>_testing` de alguma conexão deste projeto;
     * - a trava dela está livre — e o coletor a SEGURA enquanto apaga, para nenhum
     *   `auto` a sortear no meio;
     * - a tabela mais nova tem mais de `ORPHAN_AFTER_SECONDS`.
     *
     * Roda em toda execução que reconstrói o banco, inclusive na lane padrão. Falha
     * aqui nunca para a suíte: o coletor é faxina, não pré-condição.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    private static function reapAbandoned($app): void
    {
        try {
            $current = self::current();
            $connections = (array) $app['config']->get('database.connections', []);

            // O nome de cada conexão como o `phpunit.xml` o declara, sem a lane desta execução.
            $bases = [];

            foreach ($connections as $name => $connection) {
                $database = is_array($connection) ? (string) ($connection['database'] ?? '') : '';

                if (str_ends_with($database, self::TESTING_SUFFIX)) {
                    $bases[$name] = self::withoutLane($database, $current);
                }
            }

            $mainBase = $bases[(string) $app['config']->get('database.default')] ?? null;

            if ($mainBase === null) {
                return;
            }

            $lanes = [];

            // ⚠️ Só os servidores das conexões de TESTE. Conexão que aponta para outro
            // lugar — um banco legado remoto, um alvo `unreachable_*`
            // — não recebe nem um `SHOW DATABASES` desta classe.
            foreach (self::existingDatabases(array_intersect_key($connections, $bases)) as $database) {
                // Um nome padrão de outra conexão pode casar o padrão de lane de uma
                // terceira (`foo_testing` x `foo_bar_testing`): nunca é candidato.
                if (in_array($database, $bases, true)) {
                    continue;
                }

                foreach ($bases as $base) {
                    $lane = self::laneOf($database, $base);

                    if ($lane !== null && $lane !== $current) {
                        $lanes[$lane] = true;
                    }
                }
            }

            $reaped = [];

            foreach (array_keys($lanes) as $lane) {
                $lane = (string) $lane;
                $handle = self::openLock(self::lockPath(self::databaseFor($mainBase, $lane)));

                if ($handle === false) {
                    continue;
                }

                if (! flock($handle, LOCK_EX | LOCK_NB)) {
                    fclose($handle);

                    continue;
                }

                $laneConnections = [];

                foreach ($bases as $name => $base) {
                    $laneConnections[$name] = ['database' => self::databaseFor($base, $lane)] + $connections[$name];
                }

                $old = self::isOldEnough($laneConnections);

                if ($old) {
                    self::dropLane($laneConnections, $lane);
                    $reaped[] = $lane;
                }

                // ⚠️ A trava de lane do POOL nunca é apagada: quem abriu o arquivo antes do
                // `unlink()` travaria o inode morto enquanto o próximo `auto` trava um novo
                // — duas execuções na mesma lane. Fora do pool o nome é de uma execução só,
                // e deixar o arquivo seria o mesmo vazamento, em miniatura.
                if ($old && ! in_array($lane, self::POOL, true)) {
                    @unlink(self::lockPath(self::databaseFor($mainBase, $lane)));
                }

                flock($handle, LOCK_UN);
                fclose($handle);
            }

            if ($reaped !== []) {
                sort($reaped);
                fwrite(STDERR, PHP_EOL.'🧹 Lanes abandonadas apagadas: '.implode(', ', $reaped).PHP_EOL);
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, PHP_EOL."⚠️  O coletor de lanes abandonadas falhou e a suíte segue: {$e->getMessage()}".PHP_EOL);
        }
    }

    /**
     * Todos os bancos `*_testing` que os servidores das conexões têm hoje.
     *
     * @param  array<string, mixed>  $connections
     * @return list<string>
     */
    private static function existingDatabases(array $connections): array
    {
        $names = [];
        $seen = [];

        foreach ($connections as $connection) {
            if (! is_array($connection)) {
                continue;
            }

            $driver = $connection['driver'] ?? '';
            $server = $driver.'|'.($connection['dsn'] ?? ($connection['host'] ?? '').':'.($connection['port'] ?? ''));

            if (isset($seen[$server])) {
                continue;
            }

            $seen[$server] = true;

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $names = array_merge($names, self::pdoFor($connection)
                    ->query("SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE '%\\_testing'")
                    ->fetchAll(PDO::FETCH_COLUMN));
            } elseif ($driver === 'mongodb' && ($client = self::mongoFor($connection)) !== null) {
                foreach ($client->listDatabaseNames() as $name) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique(array_filter(
            $names,
            fn ($name) => is_string($name) && str_ends_with($name, self::TESTING_SUFFIX)
        )));
    }

    /**
     * A tabela MySQL mais nova da lane é mais velha que `ORPHAN_AFTER_SECONDS`?
     *
     * Lane sem tabela nenhuma — só banco Mongo, ou banco MySQL vazio — conta como
     * velha. A idade é medida pelo relógio do servidor, que é o mesmo que gravou o
     * `create_time`: o do container pode estar em outro fuso.
     *
     * @param  array<string, mixed>  $laneConnections
     */
    private static function isOldEnough(array $laneConnections): bool
    {
        foreach ($laneConnections as $connection) {
            if (($connection['driver'] ?? '') !== 'mysql' && ($connection['driver'] ?? '') !== 'mariadb') {
                continue;
            }

            $statement = self::pdoFor($connection)->prepare(
                'SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(MAX(create_time)) FROM information_schema.tables WHERE table_schema = ?'
            );
            $statement->execute([$connection['database']]);
            $age = $statement->fetchColumn();

            if ($age !== null && $age !== false && (int) $age < self::ORPHAN_AFTER_SECONDS) {
                return false;
            }
        }

        return true;
    }

    /** Conexão crua ao servidor, sem banco, com espera curta por lock. */
    private static function pdoFor(array $connection): PDO
    {
        $pdo = new PDO(
            "mysql:host={$connection['host']};port=".($connection['port'] ?? 3306),
            (string) ($connection['username'] ?? ''),
            (string) ($connection['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        // Um `DROP` preso atrás de uma transação aberta vira aviso em segundos, não
        // uma suíte parada pelo `lock_wait_timeout` padrão, que é de um dia.
        $pdo->exec('SET SESSION lock_wait_timeout = 10');

        return $pdo;
    }

    /** Cliente Mongo da conexão. Null em projeto sem a biblioteca ou sem DSN. */
    private static function mongoFor(array $connection): ?\MongoDB\Client
    {
        if (! class_exists(\MongoDB\Client::class) || empty($connection['dsn'])) {
            return null;
        }

        return new \MongoDB\Client((string) $connection['dsn']);
    }

    /**
     * Encerra o processo com a mensagem — e não lança.
     *
     * ⚠️ **Exceção aqui não para nada.** `acquire()` roda dentro do
     * `createApplication()`, que o PHPUnit chama por TESTE: uma exceção vira erro
     * daquele caso e a execução segue para o próximo, repetindo o mesmo erro uma vez
     * por teste. A linha "Rode numa lane livre" sairia do scroll no primeiro segundo, e
     * o operador veria a suíte inteira em vermelho — que é precisamente o sintoma que esta
     * classe existe para substituir por uma frase.
     *
     * Daí o `exit`. É o único ponto do código de teste onde ele aparece, e a troca é
     * deliberada: a alternativa não é "falhar de forma mais limpa", é voltar ao
     * despejo ilegível.
     */
    private static function abort(string $message): never
    {
        fwrite(STDERR, PHP_EOL.$message.PHP_EOL);

        exit(1);
    }

    /** Quem está na lane, há quanto tempo, e para onde ir. */
    private static function busyMessage(string $path, string $database): string
    {
        $holder = json_decode((string) @file_get_contents($path), true);

        // ⚠️ O arquivo diz quem ESCREVEU por último, não quem segura o `flock`. Na
        // colisão real são o mesmo processo — `hold()` escreve logo depois de
        // travar —, mas não quando a trava é segurada por outro meio (um `flock -x`
        // na mão) ou quando o registro ficou de uma rodada anterior. Citar um PID
        // morto é pior que não citar nenhum: é o primeiro `ps` que o operador dá, e
        // ele não vai achar nada.
        $pid = is_array($holder) ? (int) ($holder['pid'] ?? 0) : 0;
        $alive = $pid > 0 && self::processIsAlive($pid);

        $who = $alive ? "PID {$pid}" : 'outro processo';

        $since = $alive && is_array($holder) && isset($holder['started_at'])
            ? ', há '.max(1, time() - (int) $holder['started_at']).'s'
            : '';

        $lane = self::current();
        $laneLabel = $lane === '' ? 'padrão' : "`{$lane}`";

        return "A lane {$laneLabel} já está em uso ({$who}{$since}), no banco `{$database}`.\n\n"
            ."Duas suítes no mesmo banco se destroem: o `migrate:fresh` de uma derruba as\n"
            ."tabelas sob os pés da outra, e as duas passam a reprovar com `Base table or\n"
            ."view not found` em testes sem relação com o que você mexeu.\n\n"
            .self::wayOut($database);
    }

    /**
     * Alguma lane do pool com a trava mesmo livre?
     *
     * ⚠️ Tem que olhar o arquivo REAL da trava, e é por isso que recebe o banco em
     * uso: a trava é por banco, não por lane. Uma versão anterior sondava um
     * `*.probe` que trava nenhuma usava, então a sugestão nunca era verificada e
     * mandava a próxima sessão para uma lane ocupada.
     */
    private static function poolHasFreeLane(string $database): bool
    {
        $current = self::current();
        $base = self::withoutLane($database, $current);

        foreach (self::POOL as $lane) {
            if ($lane !== $current && self::isLockFree(self::lockPath(self::databaseFor($base, $lane)))) {
                return true;
            }
        }

        return false;
    }

    /** O processo ainda existe? `/proc` primeiro, que é o que o container tem. */
    private static function processIsAlive(int $pid): bool
    {
        if (is_dir('/proc')) {
            return is_dir("/proc/{$pid}");
        }

        return function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    /**
     * A saída: o `auto`, se o pool tem vaga, ou a verdade de que não tem.
     *
     * ⚠️ Nunca uma lane da lista "como consolo" quando a sonda acabou de vê-la
     * ocupada: mandaria a próxima sessão bater na mesma porta — e desmentiria a
     * mensagem no mesmo parágrafo em que ela é dada.
     */
    private static function wayOut(string $database): string
    {
        if (self::poolHasFreeLane($database)) {
            return "Rode numa lane livre:  TEST_LANE=auto composer test\n";
        }

        return 'As lanes do pool ('.implode(', ', self::POOL).") também estão ocupadas.\n"
            ."Espere uma terminar e rode de novo:  TEST_LANE=auto composer test\n";
    }

    /** O banco sem o apelido de lane — o nome como o `phpunit.xml` o declara. */
    private static function withoutLane(string $database, string $lane): string
    {
        if ($lane === '' || ! str_ends_with($database, '_'.$lane.self::TESTING_SUFFIX)) {
            return $database;
        }

        return substr($database, 0, -strlen('_'.$lane.self::TESTING_SUFFIX)).self::TESTING_SUFFIX;
    }

    /**
     * Abre o arquivo de trava, deixando-o acessível a qualquer usuário.
     *
     * ⚠️ O `chmod` é o ponto todo, e vale para a SONDA também. A trava é
     * compartilhada entre execuções que podem rodar com usuários diferentes
     * (`docker exec` como root, `docker exec -u www-data`), e com umask 0022 o
     * arquivo nasce 0644 de quem o criou primeiro. Deixar a sonda criar um 0644 era
     * pior que não sondar: a mensagem mandava o operador para a lane `c` e lá a
     * trava já estava inacessível para ele — a própria sugestão produzindo a
     * execução desprotegida que ela existe para evitar. O diretório, pelo mesmo
     * motivo, nasce 0777.
     *
     * @return resource|false
     */
    private static function openLock(string $path)
    {
        $directory = dirname($path);

        if (! is_dir($directory) && @mkdir($directory, 0777, true)) {
            @chmod($directory, 0777);
        }

        $handle = @fopen($path, 'c+');

        if ($handle !== false) {
            @chmod($path, 0666);
        }

        return $handle;
    }

    /** Testa a trava sem ficar com ela. */
    private static function isLockFree(string $path): bool
    {
        $handle = self::openLock($path);

        if ($handle === false) {
            return false;
        }

        $free = flock($handle, LOCK_EX | LOCK_NB);

        if ($free) {
            flock($handle, LOCK_UN);
        }

        fclose($handle);

        return $free;
    }

    /**
     * Variável de ambiente, de `getenv()` ou de `$_ENV`.
     *
     * ⚠️ Sem `?:` de propósito: `'0'` é uma lane válida e é FALSY em PHP, então
     * `getenv('TEST_LANE') ?: $_ENV[...]` a descartaria — e a execução cairia na
     * lane padrão convencida de estar isolada, que é o defeito que esta classe existe
     * para impedir.
     */
    private static function env(string $name): string
    {
        $raw = getenv($name);

        if ($raw === false) {
            $raw = $_ENV[$name] ?? '';
        }

        return (string) $raw;
    }

    /**
     * Cria os bancos MySQL da lane que ainda não existem.
     *
     * Lane nova não tem banco, e `migrate:fresh` não cria o seu — ele falha com
     * "Unknown database", que manda procurar no lugar errado. Mongo fica de fora:
     * lá a base nasce na primeira escrita.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    private static function createMissingDatabases($app): void
    {
        foreach ((array) $app['config']->get('database.connections', []) as $connection) {
            if (! is_array($connection) || ($connection['driver'] ?? '') !== 'mysql') {
                continue;
            }

            $database = (string) ($connection['database'] ?? '');

            // Só cria o que a `TestingDatabaseGuard` deixaria passar depois.
            if (! str_ends_with($database, self::TESTING_SUFFIX)) {
                continue;
            }

            try {
                self::pdoFor($connection)->exec(
                    "CREATE DATABASE IF NOT EXISTS `{$database}` "
                    .'CHARACTER SET '.($connection['charset'] ?? 'utf8mb4')
                    .' COLLATE '.($connection['collation'] ?? 'utf8mb4_unicode_ci')
                );
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "Não foi possível criar o banco `{$database}` da lane: {$e->getMessage()}"
                );
            }
        }
    }
}
