<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agente\AgentePeajeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgenteController extends Controller
{
    /** POST /api/v1/agente/chat (mensaje y/o imagen, historial opcional). También lo usa la burbuja web. */
    public function chat(Request $request, AgentePeajeService $agente): JsonResponse
    {
        $datos = $request->validate([
            'mensaje' => ['nullable', 'string', 'max:2000', 'required_without:imagen'],
            'imagen' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'required_without:mensaje'],
            'historial' => ['nullable'],
        ]);
        $historial = $datos['historial'] ?? [];
        if (is_string($historial)) {
            $historial = json_decode($historial, true) ?: [];
        }
        $imagen = $request->hasFile('imagen') ? (string) file_get_contents($request->file('imagen')->getRealPath()) : null;

        return response()->json($agente->responder($datos['mensaje'] ?? null, $imagen, $request->user(), (array) $historial));
    }
}
