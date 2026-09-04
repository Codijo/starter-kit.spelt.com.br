<?php

namespace App\Providers;

use App\Contracts\ProductProvisioner;
use App\Models\Core\Account\PersonalAccessToken;
use App\Services\Ai\AiProvider;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Spelt\NullProductProvisioner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cada produto que copia o kit registra AQUI o seu provisioner
        // (App\Contracts\ProductProvisioner). O padrão é o no-op. Ver spec §10.
        $this->app->bind(ProductProvisioner::class, NullProductProvisioner::class);

        // IA (LLM): o AiProvider roteia p/ o driver do perfil (config/ai.php).
        // Injete o AiProvider p/ escolher o perfil; ou o AiDriver p/ o perfil default.
        $this->app->singleton(AiProvider::class);
        $this->app->bind(AiDriver::class, fn ($app) => $app->make(AiProvider::class)->driver());
    }

    public function boot(): void
    {
        // Modelo de token custom — captura IP/User-Agent no createToken() (Sessões ativas).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->loadMigrations();
    }

    /**
     * Carrega migrations recursivamente — organizadas por domínio, seguindo os Models
     * (ex.: `database/migrations/Core/Account/...`). Mesmo padrão do Spelt.
     */
    protected function loadMigrations(): void
    {
        $paths = collect(File::allFiles(database_path('migrations')))
            ->map(fn ($file) => $file->getPath())
            ->unique()
            ->values()
            ->all();

        if (! empty($paths)) {
            $this->loadMigrationsFrom($paths);
        }
    }
}
