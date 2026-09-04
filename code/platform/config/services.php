<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API do produto (o kit API)
    |--------------------------------------------------------------------------
    | O Platform NÃO fala com o Spelt direto — fala com a API do próprio produto,
    | que por sua vez integra com o Spelt. Ver docs/technical/starter-kit.md.
    */
    'api' => [
        'url' => env('PRODUCT_API_URL', 'http://localhost:8000'),
    ],

    'product' => [
        'name' => env('PRODUCT_NAME', 'Meu Produto'),
        'logo' => env('PRODUCT_LOGO'),
    ],

];
