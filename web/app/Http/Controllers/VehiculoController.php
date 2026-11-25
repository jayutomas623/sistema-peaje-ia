<?php

namespace App\Http\Controllers;

use App\Models\Tarifa;
use App\Models\Vehiculo;
use App\Services\PlacaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehiculoController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $vehiculos = Vehiculo::withCount('pasos')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('placa', 'like', '%'.PlacaService::normalizar($q).'%')
                ->orWhereLike('propietario', "%{$q}%")))   // whereLike = ILIKE en pgsql (insensible a mayúsculas)
            ->when($request->query('categoria'), fn ($w, $c) => $w->where('categoria', $c))
            ->orderBy('placa')->paginate(15)->withQueryString();

        return view('vehiculos.index', compact('vehiculos', 'q'));
    }

    public function show(Vehiculo $vehiculo): View
    {
        $pasos = $vehiculo->pasos()->with('caseta')->latest()->paginate(10);

        return view('vehiculos.show', compact('vehiculo', 'pasos'));
    }

    public function create(): View
    {
        return view('vehiculos.form', ['vehiculo' => new Vehiculo(['categoria' => 'auto', 'ejes' => 2, 'activo' => true, 'saldo' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = Vehiculo::create($this->validar($request));

        return redirect()->route('vehiculos.show', $v)->with('ok', "Vehículo {$v->placa} registrado.");
    }

    public function edit(Vehiculo $vehiculo): View
    {
        return view('vehiculos.form', compact('vehiculo'));
    }

    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $vehiculo->update($this->validar($request, $vehiculo));

        return redirect()->route('vehiculos.show', $vehiculo)->with('ok', "Vehículo {$vehiculo->placa} actualizado.");
    }

    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $placa = $vehiculo->placa;
        $vehiculo->delete();

        return redirect()->route('vehiculos.index')->with('ok', "Vehículo {$placa} eliminado (sus pasos se conservan como no vinculados).");
    }

    private function validar(Request $request, ?Vehiculo $v = null): array
    {
        $request->merge(['placa' => PlacaService::normalizar($request->input('placa')), 'activo' => $request->boolean('activo')]);

        return $request->validate([
            'placa' => ['required', 'regex:'.PlacaService::FORMATO, Rule::unique('vehiculos', 'placa')->ignore($v?->id)],
            'propietario' => ['required', 'string', 'max:120'],
            'ci_nit' => ['nullable', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'categoria' => ['required', Rule::in(Tarifa::CATEGORIAS)],
            'ejes' => ['required', 'integer', 'min:2', 'max:9'],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:40'],
            'anio' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'saldo' => ['required', 'numeric', 'min:0', 'max:100000'],
            'activo' => ['boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], ['placa.regex' => 'La placa debe tener formato boliviano: 3 o 4 dígitos y 3 letras (ej. 2481KLM).']);
    }
}
