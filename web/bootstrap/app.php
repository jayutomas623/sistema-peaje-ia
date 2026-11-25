<?php

use App\Http\Middleware\EnsureActivo;
use App\Http\Middleware\EnsureRol;
use App\Http\Middleware\VerificarTokenHardware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render, Cloudflare, Nginx o cPanel terminan HTTPS en un proxy: se confía en sus cabeceras
        // X-Forwarded-For/Host/Port/Proto para que request()->secure() y las URLs generadas sean https.
        // (El esquema https se fuerza además en producción en AppServiceProvider::boot.)
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'rol' => EnsureRol::class,
            'activo' => EnsureActivo::class,
            'hardware.token' => VerificarTokenHardware::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No autenticado: envíe el token Bearer obtenido en /api/v1/auth/google'], 401);
            }
        });
    })->create();
