<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Carrega automaticamente os arquivos de rota em `routes/web/**` (uma página/contexto por
 * arquivo — ex.: `routes/web/Page.php`), no grupo `web`. É o "padrão de páginas": para uma
 * página específica nova, crie a view + a rota (num arquivo em routes/web/).
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();

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
