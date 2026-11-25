<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Services\EstadisticasService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function index(): View
    {
        return view('panel.index', [
            'contadores' => EstadisticasService::contadores(),
            'pasos' => Paso::with('caseta', 'vehiculo')->latest()->limit(20)->get()->map->resumen(),
            'alertas' => Alerta::with('caseta', 'paso')->where('atendida', false)->latest()->limit(6)->get()->map->resumen(),
            'casetas' => Caseta::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    /** Respaldo por sondeo (si Pusher/Reverb no está disponible) */
    public function feed(): JsonResponse
    {
        return response()->json([
            'contadores' => EstadisticasService::contadores(),
            'pasos' => Paso::with('caseta', 'vehiculo')->latest()->limit(20)->get()->map->resumen(),
            'alertas' => Alerta::with('caseta', 'paso')->where('atendida', false)->latest()->limit(6)->get()->map->resumen(),
        ]);
    }
}
