<?php

namespace App\Http\Controllers;

use App\Models\Caseta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CasetaController extends Controller
{
    public function index(): View
    {
        $casetas = Caseta::withCount(['pasos', 'pasos as pasos_hoy_count' => fn ($q) => $q->whereDate('created_at', today())])
            ->orderBy('nombre')->get();

        return view('casetas.index', compact('casetas'));
    }

    public function create(): View
    {
        return view('casetas.form', ['caseta' => new Caseta(['activa' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Caseta::create($this->validar($request));

        return redirect()->route('casetas.index')->with('ok', 'Caseta creada.');
    }

    public function edit(Caseta $caseta): View
    {
        return view('casetas.form', compact('caseta'));
    }

    public function update(Request $request, Caseta $caseta): RedirectResponse
    {
        $caseta->update($this->validar($request, $caseta));

        return redirect()->route('casetas.index')->with('ok', 'Caseta actualizada.');
    }

    public function destroy(Caseta $caseta): RedirectResponse
    {
        if ($caseta->pasos()->exists()) {
            $caseta->update(['activa' => false]);

            return redirect()->route('casetas.index')->with('ok', 'La caseta tiene pasos registrados: se desactivó en lugar de eliminarse.');
        }
        $caseta->delete();

        return redirect()->route('casetas.index')->with('ok', 'Caseta eliminada.');
    }

    private function validar(Request $request, ?Caseta $c = null): array
    {
        $request->merge(['activa' => $request->boolean('activa'), 'codigo' => mb_strtoupper((string) $request->input('codigo'))]);

        return $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('casetas')->ignore($c?->id)],
            'nombre' => ['required', 'string', 'max:120'],
            'ubicacion' => ['nullable', 'string', 'max:200'],
            'carretera' => ['nullable', 'string', 'max:120'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'activa' => ['boolean'],
        ]);
    }
}
