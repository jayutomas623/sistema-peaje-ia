<?php

namespace App\Http\Controllers;

use App\Models\Caseta;
use App\Models\Paso;
use App\Services\PasoService;
use App\Services\PlacaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasoController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->only(['placa', 'caseta_id', 'estado', 'categoria', 'desde', 'hasta']);
        $pasos = Paso::with('caseta', 'vehiculo')
            ->when($f['placa'] ?? null, fn ($q, $p) => $q->where('placa_detectada', 'like', '%'.PlacaService::normalizar($p).'%'))
            ->when($f['caseta_id'] ?? null, fn ($q, $c) => $q->where('caseta_id', (int) $c))
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($f['categoria'] ?? null, fn ($q, $c) => $q->where('categoria_cobrada', $c))
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['hasta'] ?? null, fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->latest()->paginate(20)->withQueryString();

        $totales = [
            'monto' => (float) (clone $pasos->getCollection())->where('estado', 'autorizado')->sum('monto'),
        ];

        return view('pasos.index', [
            'pasos' => $pasos, 'f' => $f, 'totales' => $totales,
            'casetas' => Caseta::orderBy('nombre')->get(),
        ]);
    }

    /** Registro manual desde el panel web subiendo una foto (útil para la demo y operadores). */
    public function store(Request $request, PasoService $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'caseta_id' => ['required', 'exists:casetas,id'],
        ]);
        $r = $servicio->procesarFrame((string) file_get_contents($request->file('imagen')->getRealPath()),
            Caseta::findOrFail($datos['caseta_id']), $request->user(), 'web', 'manual');

        if (! ($r['registrado'] ?? false)) {
            $msg = ($r['duplicado'] ?? false) ? 'Paso duplicado (mismo vehículo en los últimos segundos): no se cobró de nuevo.'
                : 'No se registró: '.($r['mensaje'] ?? $r['motivo'] ?? 'sin resultado');

            return back()->with('error', $msg);
        }
        $p = $r['paso'];

        return back()->with('ok', "Paso registrado: placa ".($p['placa'] ?? 'ilegible')." · {$p['estado_texto']} · Bs ".number_format($p['monto'], 2));
    }
}
