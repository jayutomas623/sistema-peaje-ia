<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use App\Services\PasoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasoController extends Controller
{
    /**
     * POST /api/v1/pasos/frame  (multipart: imagen, caseta_id, modo=auto|manual)
     * Procesa un frame de la cámara de la caseta (app móvil 2–5 fps o captura manual).
     */
    public function frame(Request $request, PasoService $servicio): JsonResponse
    {
        $datos = $request->validate([
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'caseta_id' => ['required', 'integer', 'exists:casetas,id'],
            'modo' => ['nullable', 'in:auto,manual'],
        ]);
        $caseta = Caseta::findOrFail($datos['caseta_id']);
        if (! $caseta->activa) {
            return response()->json(['message' => 'La caseta está inactiva'], 422);
        }
        $resultado = $servicio->procesarFrame(
            (string) file_get_contents($request->file('imagen')->getRealPath()),
            $caseta, $request->user(), $request->is('api/*') ? 'movil' : 'web', $datos['modo'] ?? 'manual'
        );

        return response()->json($resultado, ($resultado['registrado'] ?? false) ? 201 : 200);
    }
}
