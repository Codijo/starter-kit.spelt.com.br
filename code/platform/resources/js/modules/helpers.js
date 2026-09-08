/**
 * Helpers globais usados direto nas expressões Alpine dos blades.
 *
 * Ficam em `window` de propósito: os componentes `x-shared.*` recebem EXPRESSÕES
 * como string (ex.: value="formatDate(item.created_at)") e são avaliadas no escopo
 * do Alpine, que enxerga o global. Um import por tela não resolveria.
 */

/**
 * Copia texto para a área de transferência.
 *
 * Trata os dois mundos: `navigator.clipboard` só existe em contexto seguro, e o
 * ambiente de desenvolvimento roda em HTTP puro (platform.fabricadelead.io). Sem o
 * fallback, copiar simplesmente não funcionaria em dev — e o bug só apareceria aqui.
 *
 * @returns {Promise<boolean>} true quando copiou
 */
window.copyText = async function copyText(text) {
    const value = String(text ?? '');
    if (!value) return false;

    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(value);
            return true;
        } catch {
            // cai para o fallback abaixo
        }
    }

    try {
        const el = document.createElement('textarea');
        el.value = value;
        el.setAttribute('readonly', '');
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(el);
        return ok;
    } catch {
        return false;
    }
};

/** Data + hora no formato brasileiro. Vazio vira string vazia (o componente cuida do "—"). */
window.formatDateTime = function formatDateTime(value) {
    if (!value) return '';
    const d = new Date(value);
    return Number.isNaN(d.getTime())
        ? ''
        : d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

/** Só a data. Use quando a hora não acrescenta nada (ex.: data de cadastro numa lista). */
window.formatDate = function formatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('pt-BR');
};

/**
 * Telefone brasileiro legível: (48) 3327-1166.
 *
 * O SDR lê o número para discar; a sequência crua de dígitos obriga a contar as casas. Só
 * formata 10 e 11 dígitos — qualquer outra coisa volta como veio, porque inventar máscara
 * para um número torto esconde que o dado está errado.
 */
window.formatPhone = function formatPhone(value) {
    const d = String(value ?? '').replace(/\D/g, '');
    if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
    if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    return value;
};

/**
 * Quais números de página desenhar: [1, null, 4, 5, 6, null, 20].
 *
 * O `null` é a reticência. Vinte páginas viram sete botões; duzentas continuam sete. Sem
 * isto, uma conta com muitos leads renderiza uma régua de números que atravessa a tela e
 * empurra o resto do rodapé para fora.
 *
 * Fica aqui, e não dentro do componente, porque é a única parte com regra de verdade — e é
 * a que dá para conferir sem abrir o navegador.
 */
window.pageWindow = function pageWindow(current, last, around = 1) {
    last = Math.max(Number(last) || 1, 1);
    current = Math.min(Math.max(Number(current) || 1, 1), last);

    const wanted = new Set([1, last]);
    for (let page = current - around; page <= current + around; page++) {
        if (page >= 1 && page <= last) wanted.add(page);
    }

    const pages = [];
    let previous = 0;
    for (const page of [...wanted].sort((a, b) => a - b)) {
        if (previous && page - previous > 1) pages.push(null);
        pages.push(page);
        previous = page;
    }

    return pages;
};

/**
 * Paginação de listagem — espalhe no componente Alpine (`...window.paginated(),`).
 *
 * O componente que espalha precisa ter um `load()` que mande `page: this.page` para a API e
 * guarde a resposta em `this.meta`. Quando a lista paginada não é a carga principal da tela
 * — como o histórico de entregas dentro do destino de webhook — passe o nome do método:
 * `...window.paginated('loadDeliveries'),`.
 *
 * Daí em diante:
 *
 *   - `goToPage(n)`   troca de página        → o que `<x-shared.pagination>` chama
 *   - `pageNumbers()` os números a desenhar → idem
 *   - `reload()`      volta para a primeira → use em TODO filtro, busca e ordenação
 *
 * `<x-shared.pagination>` lê tudo isto do escopo da tela, sem `x-data` próprio e sem evento:
 * espalhar o mixin É a instalação do componente. Há um teste de contrato que confere isso —
 * tela que usa o componente sem espalhar o mixin quebra a suíte.
 *
 * ⚠️ Filtro chama `reload()`, nunca `load()`. Quem está na página 3 e muda o filtro veria
 * "nenhum resultado" — porque a página 3 do novo recorte não existe — e concluiria que o
 * filtro não encontra nada.
 */
window.paginated = function paginated(loader = 'load') {
    return {
        page: 1,
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null },

        goToPage(page) {
            if (! page || page === this.meta.current_page) {
                return;
            }

            this.page = page;
            this[loader]();
        },

        /** Os números que `<x-shared.pagination>` desenha. `null` é reticência. */
        pageNumbers() {
            return window.pageWindow(this.meta.current_page, this.meta.last_page);
        },

        reload() {
            this.page = 1;
            this[loader]();
        },
    };
};

/**
 * Abre uma exportação e leva o usuário para o histórico.
 *
 * ── Por que redireciona ───────────────────────────────────────────────────
 *
 * O arquivo é o objetivo do clique. Ficar na listagem com um toast deixa a pessoa sem saber
 * onde ele vai aparecer; a tela de Exportações mostra a linha nascendo e mudando sozinha até
 * "Pronto". É o comportamento do produto que já roda em produção há tempo.
 */
window.requestExport = async function requestExport(type, format, filters = {}) {
    try {
        await window.axios.post('/api/export/exports', { type, format, filters });
        window.location.href = '/exports';
    } catch (e) {
        // 400 aqui é regra de negócio — quase sempre "já existe uma em andamento". A frase
        // vem da API para o front não guardar uma segunda cópia da regra.
        window.Alpine.store('flash').error(
            e.response?.data?.message || 'Não foi possível iniciar a exportação.'
        );
    }
};
