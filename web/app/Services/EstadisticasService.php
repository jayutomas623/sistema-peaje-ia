<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Paso;

class EstadisticasService
{
    /** Contadores del panel en tiempo real (día actual en la zona horaria de la app). */
    public static function contadores(?int $casetaId = null): array
    {
        $hoy = Paso::query()->whereDate('created_at', now()->toDateString())
            ->when($casetaId, fn ($q) => $q->where('caseta_id', $casetaId));

        return [
            'pasos_hoy' => (clone $hoy)->count(),
            'recaudacion_hoy' => round((float) (clone $hoy)->where('estado', 'autorizado')->sum('monto'), 2),
            'pasajeros_hoy' => (int) (clone $hoy)->sum('pasajeros'),
            'alertas_activas' => Alerta::where('atendida', false)
                ->when($casetaId, fn ($q) => $q->where('caseta_id', $casetaId))->count(),
            'no_registradas_hoy' => (clone $hoy)->where('estado', 'no_registrada')->count(),
            'actualizado' => now()->toIso8601String(),
        ];
    }
}
