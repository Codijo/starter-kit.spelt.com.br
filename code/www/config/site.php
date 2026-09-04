<?php

/*
|--------------------------------------------------------------------------
| Conteúdo do site (marketing)
|--------------------------------------------------------------------------
| Fonte única do que o produto customiza: marca, navegação, planos, contato.
| As páginas leem daqui via config('site.*'). env() só aqui (config/), nunca nas views.
*/

return [

    'product' => [
        'name' => env('PRODUCT_NAME', 'Meu Produto'),
        'tagline' => 'Uma frase curta que resume o valor do produto.',
        'logo' => env('PRODUCT_LOGO'), // caminho/URL; vazio = mostra o nome
    ],

    // URL da Plataforma (o app) — CTAs de "Entrar" / "Começar" apontam para cá.
    'app_url' => env('APP_PLATFORM_URL', 'http://localhost'),

    'contact_email' => env('CONTACT_EMAIL', 'contato@exemplo.com'),

    // Navegação principal (header + footer). `route` = nome de rota nomeada.
    'nav' => [
        ['label' => 'Plataforma', 'route' => 'site.platform'],
        ['label' => 'Preços', 'route' => 'site.pricing'],
        ['label' => 'Sobre', 'route' => 'site.about'],
        ['label' => 'Contato', 'route' => 'site.contact'],
    ],

    // Redes sociais no rodapé. Ex.: ['label' => 'Instagram', 'url' => 'https://...'].
    'social' => [],

    /*
    | Planos exibidos em /precos. O produto edita aqui (ou troca por dados da API).
    | `highlight` destaca o plano recomendado. `cta_url` opcional (default = app_url).
    */
    'plans' => [
        [
            'name' => 'Inicial',
            'price' => 'R$ 0',
            'period' => '/mês',
            'description' => 'Para começar e validar.',
            'features' => ['Recurso essencial um', 'Recurso essencial dois', 'Recurso essencial três'],
            'cta' => 'Começar grátis',
            'highlight' => false,
        ],
        [
            'name' => 'Pro',
            'price' => 'R$ 49',
            'period' => '/mês',
            'description' => 'Para crescer com tranquilidade.',
            'features' => ['Tudo do Inicial', 'Recurso avançado', 'Mais capacidade', 'Suporte prioritário'],
            'cta' => 'Assinar o Pro',
            'highlight' => true,
        ],
        [
            'name' => 'Escala',
            'price' => 'Sob consulta',
            'period' => '',
            'description' => 'Para times e alto volume.',
            'features' => ['Tudo do Pro', 'SLA e segurança', 'Onboarding dedicado'],
            'cta' => 'Falar com vendas',
            'cta_route' => 'site.contact',
            'highlight' => false,
        ],
    ],

];
