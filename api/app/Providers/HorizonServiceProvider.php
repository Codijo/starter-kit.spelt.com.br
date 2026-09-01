<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * O gate padrão (`viewHorizon` por e-mail) não se aplica: a API é stateless por token,
     * sem sessão de operador. A proteção real é o `HorizonBasicAuthMiddleware` (Basic Auth),
     * anexado às rotas em `config/horizon.php`. Aqui liberamos o gate para o middleware decidir.
     */
    protected function gate(): void {}

    protected function authorization(): void
    {
        Horizon::auth(fn ($request) => true);
    }
}
