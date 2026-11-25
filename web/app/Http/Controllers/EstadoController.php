<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\EventoHardware;
use App\Services\InferenciaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Estado del sistema: ping a INFERENCE_URL/health, ESP32, tiempo real y configuración sin redeploy. */
class EstadoController extends Controller
{
    public function index(InferenciaClient $ia): View
    {
        return view('estado.index', [
            'inferenceUrl' => $ia->url(),
            'tokenConfigurado' => $ia->token() !== '',
            'prioridad' => Configuracion::obtener('PRIORIDAD_CATEGORIA', config('peaje.prioridad_categoria')),
            'broadcast' => config('broadcasting.default'),
            'agente' => config('peaje.agente.proveedor'),
        ]);
    }

    public function health(InferenciaClient $ia): JsonResponse
    {
        $latido = Cache::get('esp32:latido');
        $visto = $latido ? Carbon::parse($latido['visto']) : null;

        return response()->json([
            'inferencia' => $ia->health(),
            'esp32' => [
                'conectado' => $visto && $visto->gt(now()->subSeconds(15)),
                'ultimo_contacto' => $visto?->diffForHumans(),
                'rssi' => $latido['rssi'] ?? null,
                'fw' => $latido['fw'] ?? null,
                'ultimo_evento' => EventoHardware::latest('id')->first()?->only(['id', 'tipo_senal', 'placa', 'monto', 'created_at']),
            ],
            'tiempo_real' => [
                'driver' => config('broadcasting.default'),
                'configurado' => config('broadcasting.default') !== 'pusher' || filled(config('broadcasting.connections.pusher.key')),
            ],
            'agente' => [
                'proveedor' => config('peaje.agente.proveedor'),
                'configurado' => filled(config('peaje.agente.'.config('peaje.agente.proveedor').'.api_key'))
                    || config('peaje.agente.proveedor') === 'ollama',
            ],
            'servidor' => ['php' => PHP_VERSION, 'laravel' => app()->version(), 'bd' => config('database.default'),
                'hora' => now()->format('d/m/Y H:i:s')],
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'inference_url' => ['required', 'url:https,http', 'max:255'],
            'inference_token' => ['nullable', 'string', 'max:255'],
            'prioridad_categoria' => ['required', 'in:ia,registro'],
        ]);
        Configuracion::guardar('INFERENCE_URL', rtrim($datos['inference_url'], '/'), false, 'URL del servidor de inferencia (RunPod/Colab)');
        if (filled($datos['inference_token'] ?? null)) {
            Configuracion::guardar('INFERENCE_TOKEN', $datos['inference_token'], true, 'Token X-API-Token (cifrado)');
        }
        Configuracion::guardar('PRIORIDAD_CATEGORIA', $datos['prioridad_categoria'], false, 'Categoría a cobrar: ia | registro');

        return back()->with('ok', 'Configuración guardada. Se aplica de inmediato (sin redeploy).');
    }
}
