<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * O tamanho de página do produto, num lugar só.
 *
 * ── Por que 10 ────────────────────────────────────────────────────────────
 *
 * Dez linhas cabem na tela sem rolagem em qualquer monitor de trabalho, e é o número que
 * torna a paginação VISÍVEL: com 50, a maioria das listas caberia numa página só, o controle
 * nunca apareceria, e o usuário passaria a acreditar que a lista está inteira ali.
 *
 * Antes disto cada controller escolhia o seu (20 aqui, 50 ali). O padrão não é uma opinião
 * por tela — é uma decisão de produto, e por isso mora numa constante só.
 *
 * ── Por que o teto ────────────────────────────────────────────────────────
 *
 * `per_page` vem da URL. Sem teto, `?per_page=999999` transforma qualquer listagem em
 * varredura da tabela inteira — de graça, para quem tiver o token.
 */
class Pagination
{
    public const PER_PAGE = 10;

    public const MAX_PER_PAGE = 100;

    /** Quantos por página, respeitando o pedido do cliente dentro do teto. */
    public static function perPage(Request $request): int
    {
        $asked = (int) $request->query('per_page', self::PER_PAGE);

        // Zero e negativo viriam de URL torta; o Laravel trataria `0` como "tudo".
        return $asked < 1 ? self::PER_PAGE : min($asked, self::MAX_PER_PAGE);
    }

    /**
     * O bloco `meta` que toda listagem devolve.
     *
     * `from`/`to` existem para a tela poder dizer "11-20 de 90" sem recalcular a conta — e
     * são nulos quando a página está vazia, que é o caso em que a conta daria 1-0.
     *
     * ⚠️ Aqui NÃO vai `links` em HTML, como faz o paginador do Laravel. A API devolve dado;
     * quais números desenhar (e onde entram as reticências) é decisão da tela.
     *
     * @return array<string,int|null>
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
