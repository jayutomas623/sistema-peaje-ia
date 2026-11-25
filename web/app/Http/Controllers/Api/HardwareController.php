<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventoHardware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HardwareController extends Controller
{
    /**
     * GET /api/v1/hardware/ultimo-evento  (header X-Hardware-Token)
     * Respuesta ligera para el ESP32: {id, tipo_senal: idle|inferencia|ok|alerta, placa, monto, timestamp}
     * Query opcional: caseta_id, rssi, fw (telemetría que se muestra en Estado del sistema).
     */
    public function ultimoEvento(Request $request): JsonResponse
    {
        Cache::put('esp32:latido', [
            'visto' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'rssi' => $request->query('rssi'),
            'fw' => $request->query('fw'),
            'caseta_id' => $request->query('caseta_id'),
        ], now()->addDay());

        $e = EventoHardware::query()
            ->when($request->query('caseta_id'), fn ($q, $c) => $q->where(fn ($w) => $w->where('caseta_id', $c)->orWhereNull('caseta_id')))
            ->latest('id')->first();

        $vigencia = (int) config('peaje.hardware_vigencia_segundos', 12);
        $vigente = $e && $e->created_at && $e->created_at->gt(now()->subSeconds($vigencia));

        return response()->json([
            'id' => $e?->id ?? 0,
            'tipo_senal' => $vigente ? $e->tipo_senal : 'idle',
            'placa' => $vigente ? $e->placa : null,
            'monto' => $vigente ? $e->monto : null,
            'timestamp' => ($e?->created_at ?? now())->toIso8601String(),
        ]);
    }
}
