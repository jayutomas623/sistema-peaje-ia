<?php

namespace App\Http\Controllers;

use App\Events\AlertaAtendida;
use App\Models\Alerta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AlertaController extends Controller
{
    public function index(Request $request): View
    {
        $ver = $request->query('ver', 'activas');
        $alertas = Alerta::with('caseta', 'paso', 'atendidaPor')
            ->when($ver === 'activas', fn ($q) => $q->where('atendida', false))
            ->when($ver === 'atendidas', fn ($q) => $q->where('atendida', true))
            ->when($request->query('tipo'), fn ($q, $t) => $q->where('tipo', $t))
            ->latest()->paginate(20)->withQueryString();

        return view('alertas.index', compact('alertas', 'ver'));
    }

    public function atender(Request $request, Alerta $alerta): RedirectResponse|JsonResponse
    {
        if (! $alerta->atendida) {
            $alerta->update(['atendida' => true, 'atendida_por' => $request->user()->id, 'atendida_at' => now()]);
            try {
                broadcast(new AlertaAtendida($alerta->id, Alerta::where('atendida', false)->count()));
            } catch (Throwable $e) {
                Log::notice('Broadcast no enviado: '.$e->getMessage());
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'activas' => Alerta::where('atendida', false)->count()]);
        }

        return back()->with('ok', 'Alerta marcada como atendida.');
    }
}
