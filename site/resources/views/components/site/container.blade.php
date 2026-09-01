{{-- Largura de conteúdo padrão do site. --}}
<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-content px-5 sm:px-6 lg:px-8']) }}>
    {{ $slot }}
</div>
