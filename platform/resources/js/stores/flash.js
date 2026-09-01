/**
 * Store de mensagens transitórias (toasts) — renderizado por <x-ui.toast />, que vive uma
 * única vez no layout (nenhuma página inclui host, nenhuma esquece).
 *
 * Portado do App do Spelt (resources/js/components/alpine/store/flash.js), simplificado para
 * o kit: SEM DOMPurify. O conteúdo dinâmico é ESCAPADO (toasts do produto são texto simples +
 * listas de erro de validação do Laravel, não HTML rico vindo de editor). As únicas tags são
 * os <ul>/<li> literais que montamos aqui.
 *
 * API: $store.flash.success|info|warning|error(msg) · .validation(errosDoLaravel) · .clear()
 * Opções: { timeout, blocking, title, dedupe }.
 */

// Timers ficam fora do objeto reativo (detalhe de implementação; não devem re-renderizar).
const timers = new Map();

// Tempo de vida por tipo. 0 = persistente (só sai no clique). Erro/validação nunca somem sós.
const TIMEOUT_BY_TYPE = { success: 4000, info: 6000, warning: 8000, error: 0, validation: 0 };
const TITLE_BY_TYPE = {
    success: 'Tudo certo',
    info: 'Informação',
    warning: 'Atenção',
    error: 'Algo deu errado',
    validation: 'Revise os campos',
};

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

/**
 * Normaliza string | array | objeto de erros do Laravel ({ campo: [msgs] }) em HTML seguro:
 * todo trecho dinâmico é escapado; só as tags <ul>/<li> literais são HTML.
 */
function toHtml(input) {
    if (input === null || input === undefined) return '';
    if (Array.isArray(input)) {
        const items = input.flat().filter(Boolean);
        if (items.length === 0) return '';
        if (items.length === 1) return escapeHtml(items[0]);
        return '<ul>' + items.map((i) => `<li>${escapeHtml(i)}</li>`).join('') + '</ul>';
    }
    if (typeof input === 'object') return toHtml(Object.values(input).flat());
    return escapeHtml(input);
}

export const flash = {
    items: [],
    blocker: null,
    maxVisible: 3,
    _seq: 0,

    success(message, options = {}) { return this.push('success', message, options); },
    info(message, options = {}) { return this.push('info', message, options); },
    warning(message, options = {}) { return this.push('warning', message, options); },
    error(message, options = {}) { return this.push('error', message, options); },
    validation(errors, options = {}) { return this.push('validation', errors, options); },

    push(type, message, options = {}) {
        const html = toHtml(message);
        if (!html) return null;

        const title = options.title ?? TITLE_BY_TYPE[type] ?? TITLE_BY_TYPE.info;

        // Bloqueante: canal próprio (modal central) para falhas que abortam um fluxo.
        if (options.blocking) {
            this.blocker = { type, title, message: html };
            return 'blocker';
        }

        // Repetição da mesma mensagem vira contador, não nova linha.
        if (options.dedupe !== false) {
            const existing = this.items.find((i) => i.type === type && i.message === html);
            if (existing) {
                existing.count += 1;
                this._disarm(existing.id);
                this._sync();
                return existing.id;
            }
        }

        const id = `flash-${++this._seq}`;
        this.items.push({
            id, type, title, message: html, count: 1,
            timeout: options.timeout ?? TIMEOUT_BY_TYPE[type] ?? 0,
            expanded: false, clamped: false, paused: false,
        });
        this._sync();
        return id;
    },

    dismiss(id) {
        this._disarm(id);
        this.items = this.items.filter((i) => i.id !== id);
        this._sync();
    },
    dismissBlocker() { this.blocker = null; },
    clear() {
        timers.forEach((t) => clearTimeout(t));
        timers.clear();
        this.items = [];
        this.blocker = null;
    },

    // Métodos, não getters (getters em store têm tracking inconsistente no Alpine 3).
    visible() { return this.items.slice(-this.maxVisible); },
    hiddenCount() { return Math.max(0, this.items.length - this.maxVisible); },

    pause(id) {
        const item = this.items.find((i) => i.id === id);
        if (!item) return;
        item.paused = true;
        this._disarm(id);
    },
    resume(id) {
        const item = this.items.find((i) => i.id === id);
        if (!item) return;
        item.paused = false;
        this._sync();
    },
    toggleExpanded(id) {
        const item = this.items.find((i) => i.id === id);
        if (!item) return;
        item.expanded = !item.expanded;
        if (item.expanded) this.pause(id); else this.resume(id);
    },

    // Só os toasts visíveis e não pausados têm timer (um success atrás de 3 erros não expira
    // antes de aparecer).
    _sync() {
        const visibleIds = new Set(this.visible().map((i) => i.id));
        timers.forEach((_, id) => { if (!visibleIds.has(id)) this._disarm(id); });
        this.visible().forEach((item) => {
            if (item.timeout > 0 && !item.paused && !item.expanded && !timers.has(item.id)) {
                this._arm(item);
            }
        });
    },
    _arm(item) {
        timers.set(item.id, setTimeout(() => { timers.delete(item.id); this.dismiss(item.id); }, item.timeout));
    },
    _disarm(id) {
        if (!timers.has(id)) return;
        clearTimeout(timers.get(id));
        timers.delete(id);
    },
};

export default flash;
