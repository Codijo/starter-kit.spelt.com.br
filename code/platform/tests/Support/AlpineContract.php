<?php

namespace Tests\Support;

/**
 * Lê o par blade + JS como um CONTRATO verificável.
 *
 * ── Por que existe ────────────────────────────────────────────────────────
 *
 * A tela é um par: o blade escreve `@click="toggleTerm(t)"` e o JS ao lado define
 * `toggleTerm`. Nada amarra os dois. Se o método sumir — refatoração, edição por
 * intervalo de linhas, renomeação pela metade — o blade continua compilando, a página
 * continua respondendo 200, e o erro só aparece quando alguém clica: `toggleTerm is not
 * defined`, no console do navegador do cliente.
 *
 * Aconteceu duas vezes neste produto. As duas vezes quem descobriu foi uma pessoa, abrindo
 * a tela. Esta classe é aquela conferência ad-hoc virada teste.
 *
 * ── O que serve de fonte da verdade ───────────────────────────────────────
 *
 * O próprio blade declara os scripts que o sustentam, no `@vite` do fim do `@section`.
 * Então o conjunto de definições disponíveis para uma tela não é adivinhado: é o que
 * aqueles arquivos definem, mais os globais de `resources/js/modules/helpers.js` (que o
 * `app.js` carrega em toda página). É assim que o mixin espalhado com
 * `...window.offerTermsMixin()` entra na conta sem nenhum caso especial.
 */
final class AlpineContract
{
    /**
     * Props de componente compartilhado cujo valor é expressão Alpine, não texto.
     *
     * `<x-shared.side-action action="runNow()">` é uma chamada de método tanto quanto um
     * `@click` — só que escondida atrás de um prop. Sem este mapa, metade das ações das
     * telas de detalhe ficaria fora da conferência.
     *
     * O mapa não se mantém sozinho, e por isso há um teste que o confronta com os `@props`
     * dos componentes: prop documentado como "Expressão" e ausente daqui quebra a suíte.
     *
     * @var array<string,array<int,string>>
     */
    public const EXPRESSION_PROPS = [
        'shared.export-button' => ['filters'],
        'shared.panel' => ['show'],
        'shared.side-action' => ['labelExpr', 'subtitleExpr', 'action', 'href', 'enabled', 'reason'],
        'shared.side-copy' => ['value', 'show'],
        'shared.side-info' => ['value', 'href', 'show'],
    ];

    /**
     * Nomes que podem ser chamados sem estar definidos no componente: o que a linguagem
     * e o navegador já oferecem. Tudo que NÃO está aqui tem de existir no JS da tela.
     */
    private const AMBIENT = [
        // Palavras da linguagem que o regex de chamada pega junto — `if (`, `return (`…
        'if', 'else', 'for', 'while', 'switch', 'catch', 'return', 'typeof', 'new', 'await',
        'function', 'in', 'of', 'do', 'try', 'case', 'delete', 'void', 'instanceof', 'yield',
        // Globais do runtime
        'Object', 'Array', 'JSON', 'Math', 'Number', 'String', 'Boolean', 'Date', 'Set', 'Map',
        'RegExp', 'Promise', 'Intl', 'Error', 'BigInt', 'Symbol', 'WeakMap', 'WeakSet',
        'parseInt', 'parseFloat', 'isNaN', 'isFinite', 'structuredClone', 'fetch',
        'encodeURIComponent', 'decodeURIComponent', 'encodeURI', 'decodeURI', 'btoa', 'atob',
        'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'queueMicrotask',
        'alert', 'confirm', 'prompt', 'console', 'window', 'document', 'navigator', 'location',
    ];

    /** Palavras que o extrator de definições não deve confundir com nome de método. */
    private const NOT_A_DEFINITION = [
        'if', 'else', 'for', 'while', 'switch', 'catch', 'return', 'function', 'do', 'try',
        'typeof', 'new', 'await', 'case', 'in', 'of', 'const', 'let', 'var', 'class', 'get', 'set',
    ];

    public function __construct(private readonly string $base) {}

    /**
     * As telas: blade com componente Alpine nomeado + os scripts que ele declara.
     *
     * @return array<int,array{blade:string,component:string,scripts:array<int,string>}>
     */
    public function screens(): array
    {
        $screens = [];

        foreach ($this->blades() as $blade) {
            $source = (string) file_get_contents($this->base.'/'.$blade);

            // O componente da tela é o primeiro `x-data="nome(...)"` — os `x-data="{...}"`
            // de acordeão que aparecem no meio da página são escopo local, não a tela.
            if (! preg_match('/x-data="\s*([A-Za-z_$][\w$]*)\s*\(/', $source, $m)) {
                continue;
            }

            $screens[] = [
                'blade' => $blade,
                'component' => $m[1],
                'scripts' => $this->viteScripts($source),
            ];
        }

        return $screens;
    }

    /** @return array<int,string> caminhos relativos dos blades de tela */
    public function blades(): array
    {
        return $this->filesIn('resources/views/web', '.blade.php');
    }

    /** @return array<int,string> */
    private function filesIn(string $directory, string $suffix): array
    {
        $found = [];
        $tree = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base.'/'.$directory));

        foreach ($tree as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $found[] = ltrim(str_replace($this->base, '', $file->getPathname()), '/');
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Os arquivos declarados no `@vite` do blade — string única ou array.
     *
     * É o blade que diz quais scripts o sustentam, então é dele que sai a lista. Uma tela
     * que esquecer o `@vite` não fica com "nenhuma definição encontrada": fica sem script
     * nenhum, e o teste diz isso com essas palavras.
     *
     * @return array<int,string>
     */
    public function viteScripts(string $source): array
    {
        if (! preg_match_all('/@vite\(\s*(\[[^\]]*\]|\'[^\']*\'|"[^"]*")\s*\)/', $source, $all)) {
            return [];
        }

        $paths = [];
        foreach ($all[1] as $argument) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $argument, $items);
            foreach ($items[1] as $path) {
                if (str_ends_with($path, '.js')) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Todos os JS que moram junto das telas.
     *
     * ⚠️ Um padrão de `glob()` com estrela dupla NÃO serve: no PHP ela vale por um único
     * segmento de caminho, não por "qualquer profundidade". Encontrava 1 dos 25 arquivos —
     * e o teste que depende disto passava sobre o conjunto vazio, o oposto de um alarme.
     *
     * @return array<int,string>
     */
    public function allScripts(): array
    {
        return $this->filesIn('resources/views/web', '.js');
    }

    /**
     * Toda expressão Alpine do blade: os atributos `x-*`, `@*` e `:*`, mais os props de
     * componente compartilhado que carregam expressão.
     *
     * ⚠️ `:prop` quer dizer duas coisas diferentes conforme a tag. Em elemento HTML é bind
     * do Alpine; em componente Blade (`<x-…>`) é atributo PHP — `:breadcrumbs="[['url' =>
     * route('icp.index')]]"` é PHP puro, e lê-lo como Alpine acusaria `route()` como método
     * inexistente em quase toda tela. Por isso o `:` de dentro de um `<x-…>` fica de fora.
     *
     * @return array<int,string>
     */
    public function expressions(string $source): array
    {
        $expressions = [];

        $componentTags = [];
        if (preg_match_all('/<x-[\w.-]+(?:[^>"]|"[^"]*")*?\/?>/s', $source, $tags, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($tags[0] as [$text, $offset]) {
                $componentTags[] = [$offset, $offset + strlen($text)];
            }
        }

        // Atributos Alpine. O valor pode ocupar várias linhas — daí o /s.
        preg_match_all(
            '/(?:^|\s)((?:x-|@|:)[\w:.\[\]$-]+)\s*=\s*"([^"]*)"/s',
            $source,
            $attrs,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($attrs as $attr) {
            [$name, $offset] = $attr[1];
            [$value] = $attr[2];

            if (str_starts_with($name, ':') && $this->within($offset, $componentTags)) {
                continue;
            }

            $expressions[] = $value;
        }

        // Props de componente: `<x-shared.side-action action="runNow()">`.
        preg_match_all('/<x-([\w.-]+)((?:[^>"]|"[^"]*")*?)\/?>/s', $source, $found, PREG_SET_ORDER);
        foreach ($found as [, $component, $body]) {
            foreach (self::EXPRESSION_PROPS[$component] ?? [] as $prop) {
                // `:prop` é bind de PHP; só a forma sem dois-pontos carrega expressão Alpine.
                if (preg_match('/(?:^|\s)'.preg_quote($prop, '/').'\s*=\s*"([^"]*)"/s', $body, $m)) {
                    $expressions[] = $m[1];
                }
            }
        }

        return $expressions;
    }

    /** @param array<int,array{0:int,1:int}> $spans */
    private function within(int $offset, array $spans): bool
    {
        foreach ($spans as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apaga o TEXTO das strings, preservando o que é código.
     *
     * Sem isto, `` `Sem movimento há ${d.idle_days} dia(s)` `` seria lido como uma chamada
     * a `dia()`. Português tem "dia(s)", "negócio(s)", "atividade(s)" — o suficiente para
     * encher o resultado de ruído e esconder o erro de verdade no meio.
     *
     * A interpolação de template literal (`${…}`) é código e continua; o resto some.
     */
    public function stripLiterals(string $expression): string
    {
        $out = '';
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($char !== "'" && $char !== '"' && $char !== '`') {
                $out .= $char;

                continue;
            }

            $quote = $char;

            for ($i++; $i < $length; $i++) {
                if ($expression[$i] === '\\') {
                    $i++;

                    continue;
                }

                if ($expression[$i] === $quote) {
                    break;
                }

                // `${…}` dentro de template literal: código, não texto.
                if ($quote === '`' && $expression[$i] === '$' && ($expression[$i + 1] ?? '') === '{') {
                    $depth = 1;
                    $inner = '';

                    for ($i += 2; $i < $length && $depth > 0; $i++) {
                        $depth += match ($expression[$i]) {
                            '{' => 1, '}' => -1, default => 0
                        };
                        if ($depth > 0) {
                            $inner .= $expression[$i];
                        }
                    }

                    $i--;
                    $out .= ' '.$this->stripLiterals($inner).' ';
                }
            }

            $out .= ' ';
        }

        return $out;
    }

    /**
     * Os nomes chamados como função nas expressões.
     *
     * Fica de fora o que não é método do componente: chamada encadeada (`lista.filter(`,
     * precedida de ponto), mágica do Alpine (`$store`, `$refs`, `$dispatch`) e o ambiente.
     *
     * @param  array<int,string>  $expressions
     * @return array<int,string>
     */
    public function calls(array $expressions): array
    {
        $names = [];

        foreach ($expressions as $expression) {
            // `x-data="icpsShow('{{ $id }}')"` — o Blade não é JS; sai antes da varredura.
            $clean = $this->stripLiterals((string) preg_replace('/\{\{.*?\}\}/s', "''", $expression));

            preg_match_all('/(?<![\w.$])([A-Za-z_$][\w$]*)\s*\(/', $clean, $found);

            foreach ($found[1] as $name) {
                if (str_starts_with($name, '$') || in_array($name, self::AMBIENT, true)) {
                    continue;
                }
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * O que os scripts da tela definem: métodos, propriedades e globais.
     *
     * Coleta com folga de propósito — definição a mais só afrouxa o teste, definição a
     * menos acusaria erro onde não há.
     *
     * @param  array<int,string>  $scripts  caminhos relativos à raiz do projeto
     * @return array<int,string>
     */
    public function definitions(array $scripts): array
    {
        $names = [];

        foreach ($scripts as $script) {
            $path = $this->base.'/'.$script;
            if (! is_file($path)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            // Método (`carregar() {`, `async salvar() {`, `get total() {`)
            preg_match_all('/^\s*(?:async\s+|\*\s*|get\s+|set\s+)*([A-Za-z_$][\w$]*)\s*\([^)]*\)\s*\{/m', $source, $m);
            // Propriedade de objeto (`terms: []`) — vira método quando recebe função
            preg_match_all('/^\s*([A-Za-z_$][\w$]*)\s*:/m', $source, $p);
            // Função ou constante de módulo
            preg_match_all('/(?:function|const|let|var)\s+([A-Za-z_$][\w$]*)/', $source, $f);
            // Global (`window.offerTermsMixin = function ...`)
            preg_match_all('/window\.([A-Za-z_$][\w$]*)\s*=/', $source, $g);

            foreach ([$m[1], $p[1], $f[1], $g[1]] as $group) {
                foreach ($group as $name) {
                    if (! in_array($name, self::NOT_A_DEFINITION, true)) {
                        $names[$name] = true;
                    }
                }
            }
        }

        return array_keys($names);
    }

    /**
     * Métodos definidos em `x-data="{ ... }"` inline no próprio blade — escopo local que
     * um filho pode chamar sem que exista no JS.
     *
     * @return array<int,string>
     */
    public function inlineDefinitions(string $source): array
    {
        $names = [];

        preg_match_all('/x-data="\s*\{(.*?)\}\s*"/s', $source, $inline);
        foreach ($inline[1] as $body) {
            preg_match_all('/([A-Za-z_$][\w$]*)\s*[:(]/', $body, $found);
            foreach ($found[1] as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /** Os componentes registrados por `Alpine.data('nome', …)` nos scripts da tela. */
    public function registrations(array $scripts): array
    {
        $names = [];

        foreach ($scripts as $script) {
            $path = $this->base.'/'.$script;
            if (! is_file($path)) {
                continue;
            }
            preg_match_all('/Alpine\.data\(\s*[\'"]([\w$]+)[\'"]/', (string) file_get_contents($path), $m);
            foreach ($m[1] as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
