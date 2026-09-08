/**
 * Exportações › O histórico.
 *
 * ── Por que a tela pergunta sozinha ───────────────────────────────────────
 *
 * O arquivo nasce numa fila: quem chega aqui logo depois de clicar em "Exportar" vê "Na
 * fila", e sem atualização automática ficaria olhando para uma tela parada, apertando F5. O
 * ciclo só roda enquanto existe pedido em aberto — terminou, para.
 *
 * O intervalo é do tamanho do trabalho: exportação típica leva segundos, e cinco segundos é
 * curto o bastante para parecer instantâneo sem transformar a tela num gerador de requisição.
 */
function exportsIndex() {
    return {
        ...window.paginated(),

        items: [],
        types: [],
        statuses: [],
        loading: true,
        removing: null,
        type: '',
        status: '',

        timer: null,

        async load() {
            this.loading = true;
            try {
                const { data } = await window.axios.get('/api/export/exports', {
                    params: {
                        page: this.page,
                        type: this.type || undefined,
                        status: this.status || undefined,
                    },
                });
                this.items = data.data.items;
                this.meta = data.data.meta;
                this.types = data.data.types;
                this.statuses = data.data.statuses;
                this.watch();
            } catch (e) {
                this.$store.flash.error('Não foi possível carregar as exportações.');
            } finally {
                this.loading = false;
            }
        },

        /** Recarrega em silêncio: o "Carregando…" piscando a cada cinco segundos é pior que esperar. */
        async refresh() {
            try {
                const { data } = await window.axios.get('/api/export/exports', {
                    params: { page: this.page, type: this.type || undefined, status: this.status || undefined },
                });
                this.items = data.data.items;
                this.meta = data.data.meta;
                this.watch();
            } catch (e) {
                this.stop();
            }
        },

        hasOpen() {
            return this.items.some((i) => i.open);
        },

        /** Liga e desliga o ciclo conforme haja o que esperar. */
        watch() {
            if (this.hasOpen() && ! this.timer) {
                this.timer = setInterval(() => this.refresh(), 5000);
            } else if (! this.hasOpen()) {
                this.stop();
            }
        },

        stop() {
            clearInterval(this.timer);
            this.timer = null;
        },

        // ── Ações ────────────────────────────────────────────────────────────────

        /**
         * O download não é um link: precisa do cabeçalho de autorização.
         *
         * Baixa como blob e entrega ao navegador. Um `<a href>` direto chegaria à API sem o
         * Bearer e voltaria 401 — com o navegador mostrando uma página de erro no lugar do
         * arquivo.
         */
        async download(item) {
            try {
                const response = await window.axios.get(`/api/export/exports/${item.id}/download`, {
                    responseType: 'blob',
                });

                const url = URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = item.file_name;
                link.click();
                URL.revokeObjectURL(url);
            } catch (e) {
                this.$store.flash.error('Esse arquivo não está mais disponível. Peça a exportação de novo.');
                this.load();
            }
        },

        confirmRemove(item) {
            this.$store.confirm.open({
                title: 'Excluir exportação',
                message: 'O arquivo é apagado agora. O que ele continha continua no produto.',
                confirmText: 'Excluir',
                danger: true,
                onConfirm: () => this.remove(item),
            });
        },

        async remove(item) {
            this.removing = item.id;
            try {
                await window.axios.delete(`/api/export/exports/${item.id}`);
                await this.load();
                this.$store.flash.success('Exportação excluída.');
            } catch (e) {
                this.$store.flash.error(e.response?.data?.message || 'Não foi possível excluir.');
            } finally {
                this.removing = null;
            }
        },

        // ── Apresentação ─────────────────────────────────────────────────────────

        fileSize(bytes) {
            if (! bytes) return '—';
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1048576) return `${Math.round(bytes / 1024)} KB`;

            return `${(bytes / 1048576).toFixed(1)} MB`;
        },

        /** Quantos filtros o pedido carregava — é o que explica o tamanho do arquivo. */
        filterCount(item) {
            return Object.values(item.filters ?? {}).filter((v) => v !== null && v !== '').length;
        },

        expiryLabel(item) {
            if (! item.expires_at) return '';
            if (item.expired) return 'expirado';

            const days = Math.ceil((new Date(item.expires_at) - new Date()) / 86400000);

            return days <= 1 ? 'expira hoje' : `expira em ${days} dias`;
        },
    };
}

document.addEventListener('alpine:init', () => window.Alpine.data('exportsIndex', exportsIndex));
