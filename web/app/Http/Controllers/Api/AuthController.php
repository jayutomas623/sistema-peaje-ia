<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\GoogleTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AuthController extends Controller
{
    /** POST /api/v1/auth/google  {id_token} → Bearer token Sanctum + rol (solo lista blanca activa). */
    public function google(Request $request, GoogleTokenVerifier $verifier): JsonResponse
    {
        $datos = $request->validate([
            'id_token' => ['required', 'string', 'min:20'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $perfil = $verifier->verificar($datos['id_token']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $usuario = Usuario::autorizado($perfil['email']);
        if (! $usuario) {
            return response()->json([
                'message' => 'Acceso denegado: cuenta no autorizada',
                'email' => $perfil['email'],
            ], 403);
        }

        $usuario->forceFill([
            'google_id' => $usuario->google_id ?: $perfil['google_id'],
            'avatar' => $perfil['avatar'] ?? $usuario->avatar,
            'nombre' => $usuario->nombre ?: $perfil['nombre'],
            'ultimo_acceso_at' => now(),
        ])->save();

        $token = $usuario->createToken($datos['dispositivo'] ?? 'app-movil', [$usuario->rol], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token' => $token,
            'tipo' => 'Bearer',
            'rol' => $usuario->rol,
            'usuario' => $usuario->only(['id', 'nombre', 'email', 'rol', 'avatar']),
        ]);
    }

    public function yo(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $request->user()->only(['id', 'nombre', 'email', 'rol', 'avatar'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }
}
