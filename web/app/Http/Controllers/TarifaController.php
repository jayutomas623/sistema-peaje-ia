<?php

namespace App\Http\Controllers;

use App\Models\Tarifa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TarifaController extends Controller
{
    public function index(): View
    {
        $tarifas = Tarifa::orderByRaw("CASE categoria WHEN 'moto' THEN 1 WHEN 'auto' THEN 2 WHEN 'minibus' THEN 3 WHEN 'bus' THEN 4 ELSE 5 END")
            ->orderBy('ejes')->get();

        return view('tarifas.index', ['tarifas' => $tarifas, 'matriz' => $tarifas->groupBy('categoria')]);
    }

    public function create(): View
    {
        return view('tarifas.form', ['tarifa' => new Tarifa(['categoria' => 'auto', 'ejes' => 2, 'activa' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tarifa::create($this->validar($request));

        return redirect()->route('tarifas.index')->with('ok', 'Tarifa creada.');
    }

    public function edit(Tarifa $tarifa): View
    {
        return view('tarifas.form', compact('tarifa'));
    }

    public function update(Request $request, Tarifa $tarifa): RedirectResponse
    {
        $tarifa->update($this->validar($request, $tarifa));

        return redirect()->route('tarifas.index')->with('ok', 'Tarifa actualizada.');
    }

    public function destroy(Tarifa $tarifa): RedirectResponse
    {
        $tarifa->delete();

        return redirect()->route('tarifas.index')->with('ok', 'Tarifa eliminada.');
    }

    private function validar(Request $request, ?Tarifa $t = null): array
    {
        $request->merge(['activa' => $request->boolean('activa')]);

        return $request->validate([
            'categoria' => ['required', Rule::in(Tarifa::CATEGORIAS)],
            'ejes' => ['required', 'integer', 'min:2', 'max:6',
                Rule::unique('tarifas')->where('categoria', $request->input('categoria'))->ignore($t?->id)],
            'monto' => ['required', 'numeric', 'min:0', 'max:10000'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activa' => ['boolean'],
        ], ['ejes.unique' => 'Ya existe una tarifa para esa categoría y número de ejes.']);
    }
}
