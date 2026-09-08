<?php

use App\Support\TokenCookie;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\Support\AlpineContract;

/**
 * Toda tela do produto responde.
 *
 * As telas são cascas: o controller devolve a view e os dados chegam depois, por axios.
 * Então esta suíte não precisa de banco, de API no ar nem de navegador — e ainda assim
 * pega o que quebra uma tela inteira: rota sem view, Blade que não compila, componente
 * `<x-…>` que não existe, variável que a view espera e o controller não passa.
 *
 * O `withoutVite` desliga a resolução do manifest: o teste é da TELA, não do build. Que os
 * arquivos declarados no `@vite` existam é o AlpineContractTest quem confere, lendo o disco.
 */
beforeEach(function () {
    $this->withoutVite();
});

/** As rotas GET nomeadas atrás do guard — a lista se atualiza sozinha a cada tela nova. */
function guardedScreens(): array
{
    return collect(Router::getRoutes())
        ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
            && $route->getName()
            && in_array('auth.token', $route->gatherMiddleware(), true))
        // Parâmetro vira um valor qualquer: o controller só repassa o id para a view, e
        // quem resolve o registro é a API, no navegador.
        ->mapWithKeys(fn (Route $route) => [
            $route->getName() => '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/'),
        ])
        ->all();
}

it('encontra as telas atrás do guard', function () {
    // Sem esta âncora, um erro na montagem da lista deixaria os testes abaixo iterando sobre
    // nada e passando — o modo mais silencioso de perder cobertura.
    //
    // Acrescente aqui as rotas do SEU produto conforme elas nascerem: é o que garante que a
    // lista continua completa, e não só não-vazia.
    expect(guardedScreens())->not->toBeEmpty()
        ->and(guardedScreens())->toHaveKeys(['dashboard']);
});

it('toda tela responde com sessão', function () {
    $broken = [];

    foreach (guardedScreens() as $name => $uri) {
        $response = $this->withCookie(TokenCookie::NAME, 'sessao-de-teste')->get($uri);

        if ($response->getStatusCode() !== 200) {
            $broken[] = "{$name} ({$uri}) respondeu {$response->getStatusCode()}";
        }
    }

    expect($broken)->toBe([]);
});

it('toda tela manda o visitante sem sessão para o login', function () {
    $leaks = [];

    foreach (guardedScreens() as $name => $uri) {
        $response = $this->get($uri);

        if (! $response->isRedirect(route('landing'))) {
            $leaks[] = "{$name} ({$uri}) não redirecionou para o login";
        }
    }

    expect($leaks)->toBe([]);
});

/**
 * A ponte entre o HTML servido e o JS que existe no repositório.
 *
 * O AlpineContractTest confere o par blade↔JS lendo arquivos. Aqui a conferência é sobre o
 * que o servidor REALMENTE emitiu: se a tela renderizar um componente que nenhum script
 * registra, a página sobe em branco no navegador e o servidor não acusa nada.
 *
 * Tela sem componente nomeado não é erro — o aviso de assinatura é estático de propósito, e
 * o painel de um kit recém-clonado ainda não tem tela de produto. O caso "a tela PERDEU o
 * x-data" já é pego do outro lado: o script órfão aparece no invariante do contrato.
 */
it('cada tela renderiza um componente Alpine que existe', function () {
    $contract = new AlpineContract(base_path());
    $registered = $contract->registrations($contract->allScripts());

    $unknown = [];

    foreach (guardedScreens() as $name => $uri) {
        $html = $this->withCookie(TokenCookie::NAME, 'sessao-de-teste')->get($uri)->getContent();

        if (preg_match('/x-data="\s*([A-Za-z_$][\w$]*)\s*\(/', (string) $html, $m)
            && ! in_array($m[1], $registered, true)) {
            $unknown[] = "{$name} renderiza {$m[1]}(), que nenhum script do projeto registra";
        }
    }

    expect($unknown)->toBe([]);
});

it('o login abre sem sessão', function () {
    $this->get(route('landing'))->assertOk();
});
