<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: ->middleware('rol:administrador') o 'rol:administrador,operador' */
class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $u = $request->user();
        if (! $u || ! in_array($u->rol, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'No autorizado: se requiere rol '.implode(' o ', $roles)], 403);
            }
            abort(403, 'Esta sección requiere rol: '.implode(' o ', $roles));
        }

        return $next($request);
    }
}
