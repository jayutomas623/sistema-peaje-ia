<?php

namespace App\Http\Controllers;

use App\Services\Agente\AgentePeajeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(): View
    {
        return view('chat.index');
    }

    /** Endpoint de la página /chat y de la burbuja flotante (sesión web + CSRF). */
    public function enviar(Request $request, AgentePeajeService $agente): JsonResponse
    {
        $datos = $request->validate([
            'mensaje' => ['nullable', 'string', 'max:2000', 'required_without:imagen'],
            'imagen' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'required_without:mensaje'],
            'historial' => ['nullable', 'string'],
        ]);
        $historial = json_decode($datos['historial'] ?? '[]', true) ?: [];
        $imagen = $request->hasFile('imagen') ? (string) file_get_contents($request->file('imagen')->getRealPath()) : null;

        return response()->json($agente->responder($datos['mensaje'] ?? null, $imagen, $request->user(), $historial));
    }
}
