<footer class="border-t border-line-soft bg-canvas-raised">
    <x-site.container class="grid gap-8 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2 lg:col-span-1">
            <x-site.logo />
            <p class="mt-3 max-w-xs text-sm text-ink-mid">{{ config('site.product.tagline') }}</p>
        </div>

        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Navegação</h4>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach (config('site.nav') as $item)
                    <li><a href="{{ route($item['route']) }}" class="text-ink-mid transition-colors hover:text-ink">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Conta</h4>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ config('site.app_url') }}" class="text-ink-mid transition-colors hover:text-ink">Entrar</a></li>
                <li><a href="{{ config('site.app_url') }}" class="text-ink-mid transition-colors hover:text-ink">Criar conta</a></li>
            </ul>
        </div>

        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Contato</h4>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="mailto:{{ config('site.contact_email') }}" class="text-ink-mid transition-colors hover:text-ink">{{ config('site.contact_email') }}</a></li>
                @foreach (config('site.social') as $s)
                    <li><a href="{{ $s['url'] }}" class="text-ink-mid transition-colors hover:text-ink" target="_blank" rel="noopener">{{ $s['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
    </x-site.container>

    <div class="border-t border-line-soft">
        <x-site.container class="flex flex-col items-center justify-between gap-2 py-6 text-xs text-ink-low sm:flex-row">
            <span>© {{ date('Y') }} {{ config('site.product.name') }}. Todos os direitos reservados.</span>
            <a href="{{ route('site.home') }}" class="hover:text-ink-mid">{{ config('site.product.name') }}</a>
        </x-site.container>
    </div>
</footer>
