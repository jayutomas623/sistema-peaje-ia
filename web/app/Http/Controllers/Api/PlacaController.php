<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agente\AgentePeajeService;
use App\Services\Agente\HerramientasPeaje;
use App\Services\InferenciaClient;
use App\Services\PlacaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PlacaController extends Controller
{
    /** POST /api/v1/placas/consultar {placa} → uno de los 4 estados + sugerencias. */
    public function consultar(Request $request, HerramientasPeaje $h, AgentePeajeService $agente): JsonResponse
    {
        $datos = $request->validate(['placa' => ['required', 'string', 'max:20']]);
        $r = $h->consultarPlaca($datos['placa']);
        if (isset($r['error'])) {
            return response()->json(['message' => $r['error']], 422);
        }

        return response()->json($r + ['mensaje' => $agente->redactarPlaca($r)]);
    }

    /** POST /api/v1/placas/consultar-imagen (imagen) → lee la placa con M1 y la consulta. */
    public function consultarImagen(Request $request, InferenciaClient $ia, HerramientasPeaje $h, AgentePeajeService $agente): JsonResponse
    {
        $request->validate(['imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192']]);
        try {
            $lectura = $ia->inferirPlaca((string) file_get_contents($request->file('imagen')->getRealPath()));
        } catch (Throwable $e) {
            return response()->json(['message' => 'Servidor de inferencia no disponible: '.$e->getMessage()], 503);
        }
        $texto = $lectura['placa']['texto'] ?? null;
        if (! ($lectura['legible'] ?? false) || ! $texto) {
            return response()->json([
                'legible' => false,
                'mensaje' => 'No se encontró una placa legible. Envíe otra imagen más cercana y de frente.',
                'lectura' => $lectura['placa'] ?? null,
                'imagen_anotada' => $lectura['imagen_anotada_base64'] ?? null,
            ], 422);
        }
        $r = $h->consultarPlaca($texto);

        return response()->json($r + [
            'legible' => true,
            'lectura' => $lectura['placa'],
            'mensaje' => $agente->redactarPlaca($r),
            'imagen_anotada' => $lectura['imagen_anotada_base64'] ?? null,
        ]);
    }

    /** GET /api/v1/placas/{placa}/historial */
    public function historial(string $placa, Request $request, HerramientasPeaje $h): JsonResponse
    {
        $limite = (int) $request->query('limite', 20);

        return response()->json($h->historial(PlacaService::normalizar($placa), $limite));
    }
}
