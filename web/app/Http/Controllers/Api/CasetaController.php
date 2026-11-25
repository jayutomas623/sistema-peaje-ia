<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use App\Models\Paso;
use App\Services\Agente\HerramientasPeaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CasetaController extends Controller
{
    /** GET /api/v1/casetas (selector de la app móvil) */
    public function index(): JsonResponse
    {
        return response()->json(['casetas' => Caseta::where('activa', true)->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'ubicacion', 'carretera'])]);
    }

    /** GET /api/v1/casetas/{id}/resumen?fecha=YYYY-MM-DD */
    public function resumen(Caseta $caseta, Request $request, HerramientasPeaje $h): JsonResponse
    {
        $r = $h->resumenCaseta($caseta->id, $request->query('fecha'));
        $ultimos = Paso::where('caseta_id', $caseta->id)->latest()->limit(10)->get()->map->resumen();

        return response()->json(($r['casetas'][0] ?? []) + ['fecha' => $r['fecha'] ?? null, 'ultimos_pasos' => $ultimos]);
    }
}
