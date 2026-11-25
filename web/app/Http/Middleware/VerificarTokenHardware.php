<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Autenticación del ESP32 por header X-Hardware-Token (comparación en tiempo constante). */
class VerificarTokenHardware
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('peaje.hardware_token');
        $recibido = (string) $request->header('X-Hardware-Token', '');
        if ($esperado === '' || ! hash_equals($esperado, $recibido)) {
            return response()->json(['message' => 'Token de hardware inválido'], 401);
        }

        return $next($request);
    }
}
