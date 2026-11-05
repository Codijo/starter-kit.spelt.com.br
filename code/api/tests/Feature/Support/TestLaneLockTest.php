<?php

use Tests\Support\TestLane;

/*
|--------------------------------------------------------------------------
| A trava da lane, onde ela de fato existe
|--------------------------------------------------------------------------
|
| Este arquivo vive em `Feature` de propósito, e não junto do resto dos casos da
| `TestLane` em `tests/Unit/Support/`. A trava só é adquirida por quem reconstrói
| o banco — `TestCase::rebuildsDatabase()` —, porque recusar uma execução que não
| toca banco não protegeria nada e impediria rodar um `--filter` de Unit enquanto
| a suíte cheia roda.
|
| Ou seja: em `Unit` não há trava para observar. Teste de trava mora onde há
| trava.
|
*/

/** O banco da conexão padrão — é ele que a `TestLane` chaveia. */
function laneLockDefaultDatabase(): string
{
    return (string) config('database.connections.'.config('database.default').'.database');
}

beforeEach(function () {
    // Banco padrão sem nome de teste (o `:memory:` do SQLite) não tem lane nem
    // trava: cada processo tem o seu. O caso diz isso, em vez de reprovar ou passar vazio.
    if (! str_ends_with(laneLockDefaultDatabase(), '_testing')) {
        $this->markTestSkipped('Banco padrão sem lane (SQLite em memória): não há trava a observar.');
    }
});

it('a trava da lane foi adquirida e é exclusiva', function () {
    $database = laneLockDefaultDatabase();
    $path = TestLane::lockPath($database);

    expect($path)->toBeReadableFile();

    // ⚠️ A asserção que vale é esta, não a existência do arquivo: o lock nunca é
    // apagado e sobrevive à execução anterior, então `toBeReadableFile()` passaria
    // de forma vazia. Um segundo pedido EXCLUSIVO sobre o mesmo arquivo só falha se
    // alguém já o segura — e quem segura é o `TestLane::apply()` deste processo.
    //
    // É este caso que reprova se a chamada sair do `TestCase`.
    $handle = fopen($path, 'c+');

    // O `TestLane` tolera não conseguir abrir a trava (avisa em STDERR e segue sem
    // proteção). Sem esta guarda, esse cenário faria o teste estourar com
    // `TypeError: flock(): Argument #1 must be of type resource, bool given` — um
    // erro que não diz nada sobre a trava e manda procurar no lugar errado.
    expect($handle)->not->toBeFalse(
        "Não foi possível abrir `{$path}`: a suíte está rodando sem proteção de lane."
    );

    $acquired = flock($handle, LOCK_EX | LOCK_NB);

    if ($acquired) {
        flock($handle, LOCK_UN);
    }

    fclose($handle);

    expect($acquired)->toBeFalse();
});

it('o banco em uso corresponde à lane desta execução', function () {
    $lane = TestLane::current();
    $database = laneLockDefaultDatabase();

    // Com lane, o apelido TEM que estar no nome — é aqui que o `renameConnections()`
    // prova ter corrido. Sem lane não há o que afirmar além do sufixo de teste.
    if ($lane !== '') {
        expect($database)->toBe(TestLane::databaseFor(
            str_replace("_{$lane}_testing", '_testing', $database),
            $lane,
        ))->toContain("_{$lane}_testing");
    }

    // E, com lane ou sem ela, o alvo continua sendo um banco de teste — é o que a
    // `TestingDatabaseGuard` exige logo depois, no mesmo `createApplication()`.
    expect($database)->toEndWith('_testing');
});

it('com TEST_LANE=auto, a lane sorteada é do pool', function () {
    // Sem `auto` não há sorteio para observar — e o caso diz isso, em vez de passar vazio.
    if (TestLane::requested() !== TestLane::AUTO) {
        $this->markTestSkipped('Rode com TEST_LANE=auto para exercitar o sorteio.');
    }

    expect(TestLane::current())->toBeIn(TestLane::POOL);
});
