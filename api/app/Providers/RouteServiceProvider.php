<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Carrega automaticamente os arquivos de rota em `routes/api/**` (organizados por domínio,
 * seguindo os Models — ex.: `routes/api/Spelt/WebhookRoute.php`), aplicando o grupo `api`
 * + prefixo `/api`. Mesmo padrão do Spelt. Cada arquivo declara só o middleware adicional
 * (`auth:api`, `spelt.webhook`, etc.).
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->configureRateLimiting();
        $this->loadApiRoutes();
    }

    protected function loadApiRoutes(): void
    {
        $path = base_path('routes/api');

        if (! File::isDirectory($path)) {
            return;
        }

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() === 'php') {
                Route::middleware('api')->prefix('api')->group($file->getPathname());
            }
        }
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('sso', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('spelt-webhook', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
