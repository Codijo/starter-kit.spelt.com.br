/**
 * Store de confirmação — substitui o window.confirm() do navegador por um modal próprio,
 * renderizado uma única vez no layout por <x-ui.confirm-modal />. Mesmo padrão do store
 * `flash`: host único + API global.
 *
 * Uso:
 *   this.$store.confirm.open({
 *     title: 'Excluir marca',
 *     message: 'Esta ação não pode ser desfeita.',
 *     confirmText: 'Excluir',
 *     danger: true,
 *     onConfirm: () => this.doRemove(),   // pode ser async; o modal mostra loading
 *   });
 */
export const confirm = {
    isOpen: false,
    loading: false,
    title: '',
    message: '',
    confirmText: 'Confirmar',
    cancelText: 'Cancelar',
    danger: false,
    _onConfirm: null,

    open(options = {}) {
        this.title = options.title ?? 'Confirmar ação';
        this.message = options.message ?? '';
        this.confirmText = options.confirmText ?? 'Confirmar';
        this.cancelText = options.cancelText ?? 'Cancelar';
        this.danger = options.danger ?? false;
        this._onConfirm = options.onConfirm ?? null;
        this.loading = false;
        this.isOpen = true;
    },

    close() {
        if (this.loading) return; // não fecha no meio de uma ação
        this.isOpen = false;
        this._onConfirm = null;
    },

    async accept() {
        if (!this._onConfirm) {
            this.isOpen = false;
            return;
        }
        this.loading = true;
        try {
            await this._onConfirm(); // o handler cuida do próprio sucesso/erro (toast/navegação)
        } finally {
            this.loading = false;
            this.isOpen = false;
            this._onConfirm = null;
        }
    },
};

export default confirm;
