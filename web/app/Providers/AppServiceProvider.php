<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Render / Cloudflare / Nginx terminan TLS en el proxy y reenvían HTTP al contenedor.
        // trustProxies('*') (bootstrap/app.php) lee X-Forwarded-Proto; además, en producción se fuerza
        // https en TODAS las URLs generadas (callback de Google OAuth, evidencias, assets, paginación).
        if ($this->app->environment('production') || config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
