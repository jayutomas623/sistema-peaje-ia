<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Si un administrador desactiva o quita de la lista blanca a un usuario, pierde el acceso de inmediato. */
class EnsureActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && ! $u->activo) {
            if ($request->is('api/*')) {
                $u->currentAccessToken()?->delete();

                return response()->json(['message' => 'Acceso denegado: cuenta no autorizada'], 403);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('acceso.denegado')->with('email', $u->email);
        }

        return $next($request);
    }
}
