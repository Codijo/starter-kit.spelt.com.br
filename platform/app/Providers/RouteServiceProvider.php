<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Carrega automaticamente os arquivos de rota em `routes/web/**` (organizados por
 * contexto — ex.: `routes/web/Auth.php`, `routes/web/Guard.php`), no grupo `web`.
 * Mesmo padrão do App/Customer do Spelt.
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('sso', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        $path = base_path('routes/web');

        if (! File::isDirectory($path)) {
            return;
        }

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() === 'php') {
                Route::middleware('web')->group($file->getPathname());
            }
        }
    }
}
