<?php

use Tests\Support\AlpineContract;

/**
 * O contrato entre o blade e o JS ao lado dele.
 *
 * Nenhum destes testes sobe o Laravel: é leitura de arquivo. Rodam em milissegundos e
 * cobrem a classe de erro que só apareceria no console do navegador do cliente — método
 * chamado no blade que não existe no componente.
 */
beforeEach(function () {
    $this->contract = new AlpineContract(base_path());
});

/** Sempre carregados: o `app.js` os inclui em toda página. */
function globalScripts(): array
{
    return ['resources/js/modules/helpers.js'];
}

/**
 * Guarda contra a suíte passar por não achar nada.
 *
 * Todo o resto varre `screens()`. Se uma tela deixasse de ser detectada — o `x-data`
 * mudou de forma, o `@vite` sumiu, a pasta mudou de lugar — os testes seguintes
 * continuariam verdes, só que sobre um conjunto menor. O contrário de um alarme.
 *
 * Em vez de um número fixo (que envelhece a cada tela nova), a âncora é uma invariante:
 * todo JS que registra um componente Alpine pertence a alguma tela. É também por aqui que
 * aparece a tela que PERDEU o `x-data` — o script dela fica sem dono.
 */
it('alcança todo componente Alpine do produto', function () {
    $declared = [];
    foreach ($this->contract->screens() as $screen) {
        $declared = [...$declared, ...$screen['scripts']];
    }

    $registrars = array_filter(
        $this->contract->allScripts(),
        fn (string $script) => str_contains((string) file_get_contents(base_path($script)), 'Alpine.data(')
    );

    // Vale vazio: num kit recém-clonado não há componente nenhum, e o teste liga sozinho
    // quando a primeira tela de produto aparecer.
    $unreached = array_values(array_diff($registrars, $declared));

    expect($unreached)->toBe([]);
});

it('cada tela declara no @vite o script que a sustenta', function () {
    $missing = [];

    foreach ($this->contract->screens() as $screen) {
        if ($screen['scripts'] === []) {
            $missing[] = "{$screen['blade']} — x-data=\"{$screen['component']}()\" sem @vite";

            continue;
        }

        foreach ($screen['scripts'] as $script) {
            if (! is_file(base_path($script))) {
                $missing[] = "{$screen['blade']} — @vite aponta para {$script}, que não existe";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('cada componente x-data está registrado no Alpine', function () {
    $missing = [];

    foreach ($this->contract->screens() as $screen) {
        $registered = $this->contract->registrations($screen['scripts']);

        if (! in_array($screen['component'], $registered, true)) {
            $missing[] = sprintf(
                '%s — x-data="%s()" mas os scripts registram [%s]',
                $screen['blade'],
                $screen['component'],
                implode(', ', $registered) ?: 'nada'
            );
        }
    }

    expect($missing)->toBe([]);
});

/**
 * O teste que existe por causa de dois incidentes reais.
 *
 * Nas duas vezes o método foi apagado junto com um trecho vizinho, o blade continuou
 * chamando, e quem descobriu foi o Otávio clicando na tela.
 */
it('todo método chamado no blade existe no componente', function () {
    $orphans = [];

    foreach ($this->contract->screens() as $screen) {
        $source = (string) file_get_contents(base_path($screen['blade']));

        $defined = array_merge(
            $this->contract->definitions([...$screen['scripts'], ...globalScripts()]),
            $this->contract->inlineDefinitions($source),
        );

        $called = $this->contract->calls($this->contract->expressions($source));

        foreach (array_diff($called, $defined) as $name) {
            $orphans[] = "{$screen['blade']} chama {$name}(), que nenhum script da tela define";
        }
    }

    expect($orphans)->toBe([]);
});

/**
 * `<x-shared.pagination>` instala-se espalhando o mixin — e nada obriga a isso.
 *
 * O componente não recebe dado por prop: lê `meta`, `pageNumbers()` e `goToPage()` do escopo
 * da própria tela, que os ganha com `...window.paginated()`. Sem o mixin, o rodapé renderiza
 * mudo: nenhum erro no servidor, e no navegador um "Mostrando 0-0 de 0" que nunca muda.
 *
 * Conferir por `definitions()` não serviria — os métodos existem no helpers.js global, que
 * toda página carrega. O que precisa existir é o ESPALHAMENTO, no script da tela.
 */
it('toda tela que usa a paginação espalha o mixin', function () {
    $problems = [];

    foreach ($this->contract->screens() as $screen) {
        $blade = (string) file_get_contents(base_path($screen['blade']));

        $scripts = collect($screen['scripts'])
            ->map(fn (string $path) => (string) file_get_contents(base_path($path)))
            ->implode("\n");

        $spreads = str_contains($scripts, 'window.paginated(');

        if (str_contains($blade, '<x-shared.pagination') && ! $spreads) {
            $problems[] = "{$screen['blade']} usa <x-shared.pagination> sem espalhar window.paginated()";
        }

        if (! $spreads) {
            continue;
        }

        // O mixin sozinho não pagina nada: quem manda a página e guarda o `meta` é o load().
        if (! str_contains($scripts, 'this.page')) {
            $problems[] = "{$screen['component']} espalha o mixin mas nunca manda `this.page` para a API";
        }

        if (! str_contains($scripts, 'this.meta =')) {
            $problems[] = "{$screen['component']} espalha o mixin mas nunca guarda o `meta` da resposta";
        }
    }

    expect($problems)->toBe([]);
});

/**
 * O mapa de props não se mantém sozinho.
 *
 * Os componentes compartilhados documentam no `@props` quais props são expressão Alpine.
 * Prop nova documentada assim e ausente do mapa quebraria a cobertura em silêncio — as
 * chamadas escondidas atrás dela deixariam de ser conferidas.
 */
it('o mapa de props de expressão acompanha os @props dos componentes', function () {
    $problems = [];

    foreach (AlpineContract::EXPRESSION_PROPS as $component => $props) {
        $path = base_path('resources/views/components/'.str_replace('.', '/', $component).'.blade.php');

        expect($path)->toBeFile("O mapa cita {$component}, que não existe mais.");

        $source = (string) file_get_contents($path);

        foreach ($props as $prop) {
            if (! preg_match("/'".preg_quote($prop, '/')."'/", $source)) {
                $problems[] = "{$component} não declara mais o prop {$prop} — tire-o do mapa";
            }
        }

        // O caminho inverso: prop documentado como expressão e fora do mapa.
        foreach (documentedExpressionProps($source) as $prop) {
            if (! in_array($prop, $props, true)) {
                $problems[] = "{$component}::{$prop} está documentado como expressão Alpine, mas fora do mapa";
            }
        }
    }

    expect($problems)->toBe([]);
});

/**
 * Os props do `@props` documentados como expressão Alpine.
 *
 * O critério é o comentário COMEÇAR com "Expressão" — o da mesma linha, ou o primeiro da
 * sequência logo acima. Bastar a palavra aparecer no meio confundiria a documentação com
 * a declaração: o prop `label` do side-action diz "Texto fixo; use :labelExpr para
 * expressão Alpine", que é justamente o contrário.
 *
 * @return array<int,string>
 */
function documentedExpressionProps(string $source): array
{
    if (! preg_match('/@props\(\[(.*?)\]\)/s', $source, $m)) {
        return [];
    }

    $props = [];
    $above = null;

    foreach (explode("\n", $m[1]) as $line) {
        $line = trim($line);

        if (str_starts_with($line, '//')) {
            $above ??= $line;

            continue;
        }

        if (preg_match("/^'([\w]+)'/", $line, $prop)) {
            $inline = str_contains($line, '//') ? substr($line, (int) strpos($line, '//')) : '';

            foreach ([$above, $inline] as $comment) {
                if ($comment && preg_match('/^\/\/\s*Express[aã]o/iu', trim($comment))) {
                    $props[] = $prop[1];
                    break;
                }
            }
        }

        $above = null;
    }

    return $props;
}
