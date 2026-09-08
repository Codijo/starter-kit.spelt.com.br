<?php

/**
 * Catálogo comercial do produto no Spelt — a definição que o `dev:spelt-catalog` provisiona.
 *
 * ESTE É O ARQUIVO QUE VOCÊ EDITA ao copiar o kit. O command é genérico e não muda:
 * ele lê tudo daqui e cria/atualiza no Spelt (idempotente por slug, com `--dry`).
 *
 * Por que existe: o Spelt é a fonte de verdade de plano/entitlement/crédito (spec §9).
 * Sem catálogo lá, o produto não tem o que sincronizar e NADA do fluxo comercial é
 * testável — nem SSO, nem entitlement, nem crédito. É o primeiro passo de um produto novo,
 * antes do `dev:spelt-purchase`.
 *
 * Os valores abaixo são um EXEMPLO plausível. Troque tudo.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Categoria de plano  (obrigatória na prática)
    |--------------------------------------------------------------------------
    | É o que faz o produto APARECER no portal Customer do Spelt. O dashboard do
    | cliente lista categorias com `show_on_dashboard = true` e `status = active`,
    | e usa `app_url` como o link "Acessar".
    |
    | ⚠️ Sem categoria vinculada ao plano, o cliente assina, paga, é provisionado —
    | e não tem por onde entrar no produto. É uma falha silenciosa: tudo parece
    | certo até alguém tentar usar.
    |
    | Requer a External API do Spelt com os campos de dashboard em `/plan-category`
    | (`app_url`, `icon_url`, `show_on_dashboard`, `dashboard_order`, `group_name`)
    | e o `PUT /plan-category/{id}` para idempotência.
    */
    'category' => [
        'slug' => 'meu-produto',
        'name' => 'Meu Produto',
        'description' => 'Descreva em uma linha o que o cliente recebe.',
        'color' => '#4F46E5',
        'app_url' => env('PRODUCT_PLATFORM_URL'),
        'icon_url' => env('PRODUCT_ICON_URL'),
        'show_on_dashboard' => true,
        'dashboard_order' => 1,
        'group_name' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipo de crédito
    |--------------------------------------------------------------------------
    | A unidade consumível do produto. O Spelt concede e expira; o produto debita
    | ao entregar valor (spec §8.6).
    |
    | Deixe `null` se o produto for só de capacidade (sem quota consumível) — o
    | command entende e pula a concessão nos planos.
    */
    'credit_type' => [
        'slug' => 'credit',
        'name' => 'Crédito',
        'description' => 'Unidade de entrega do produto: 1 crédito = 1 operação.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    | `days` vira `trial_days` do plano; `credits` vira `trial_value` do
    | PlanCreditGrant (modo `fixed`). Use `days => 0` para catálogo sem trial.
    |
    | Dimensione o trial para ser CONSUMIDO dentro do prazo: crédito de trial que
    | sobra é COGS de aquisição pago sem o cliente ver o produto funcionando.
    */
    'trial' => [
        'days' => 7,
        'credits' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features de capacidade (entitlements)
    |--------------------------------------------------------------------------
    | O que NÃO é crédito: tetos estáticos do plano. Viram o snapshot em
    | `billing_subscriptions.entitlements`, lido por `$account->entitlement(...)`.
    |
    | Convenção de slug: `plan-feature-<coisa>`, em inglês e no SINGULAR.
    */
    'features' => [
        'plan-feature-user' => ['name' => 'Usuários', 'description' => 'Usuários vinculados à conta.'],
        // 'plan-feature-project' => ['name' => 'Projetos', 'description' => '…'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Degraus de plano
    |--------------------------------------------------------------------------
    | `credits` é a franquia recorrente (PlanCreditGrant.quantity).
    |
    | ⚠️ Sem `credits`, o cliente PAGANTE recebe 0 por ciclo e trava assim que a
    | cortesia do trial acaba (spec §8.6). Não existe coluna `value` no plano — se
    | a franquia recorrente ficar em branco, ninguém avisa.
    |
    | `note` não vai para o Spelt; é anotação de conferência.
    */
    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'price' => 97.00,
            'credits' => 100,
            'features' => [
                'plan-feature-user' => 2,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 297.00,
            'credits' => 500,
            'popular' => true,
            'features' => [
                'plan-feature-user' => 10,
            ],
        ],
    ],
];
