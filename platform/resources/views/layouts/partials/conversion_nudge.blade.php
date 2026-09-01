{{-- Banner de conversão — o PRODUTO instiga; a gestão/compra acontece no portal Customer do
     Spelt (via managePortal). Dirigido pelo $store.me.nudge(): só aparece quando há motivo
     (trial acabando, créditos baixos/zerados, assinatura inativa). Prioridade no store. --}}
<div x-data="{ get n() { return $store.me.nudge(); } }" x-show="n" x-cloak class="px-6 pt-4 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-2.5 shadow-subtle"
        :class="{
            'border-warm-orange/30 bg-warm-orange/10': n && (n.tone === 'danger' || n.tone === 'warning'),
            'border-accent-blue/30 bg-accent-blue/5': n && n.tone === 'accent',
        }">
        <div class="flex min-w-0 items-center gap-2">
            <svg class="mt-0.5 h-4 w-4 shrink-0 self-start"
                :class="{ 'text-warm-orange': n && (n.tone === 'danger' || n.tone === 'warning'), 'text-accent-blue': n && n.tone === 'accent' }"
                fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span class="text-sm font-medium text-ink-black" x-text="n?.text"></span>
        </div>
        <button type="button" @click="$store.me.managePortal($store.me.portalTarget(n.action))"
            class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium text-canvas-white transition hover:opacity-90"
            :class="{ 'bg-warm-orange': n && (n.tone === 'danger' || n.tone === 'warning'), 'bg-accent-blue': n && n.tone === 'accent' }"
            x-text="n?.cta"></button>
    </div>
</div>
