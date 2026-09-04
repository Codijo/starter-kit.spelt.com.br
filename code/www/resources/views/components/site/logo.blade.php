{{-- Logo do produto: imagem (config site.product.logo) ou o nome em serif. --}}
@if (config('site.product.logo'))
    <img src="{{ config('site.product.logo') }}" alt="{{ config('site.product.name') }}" class="h-7 w-auto">
@else
    <span class="font-display text-xl font-semibold text-ink">{{ config('site.product.name') }}</span>
@endif
