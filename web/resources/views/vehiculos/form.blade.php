@extends('layouts.app')
@section('titulo', $vehiculo->exists ? 'Editar vehículo '.$vehiculo->placa : 'Nuevo vehículo')
@section('contenido')
<form method="POST" action="{{ $vehiculo->exists ? route('vehiculos.update', $vehiculo) : route('vehiculos.store') }}" class="card card-body shadow-sm" style="max-width: 900px">
    @csrf @if ($vehiculo->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Placa *</label>
            <input name="placa" value="{{ old('placa', $vehiculo->placa) }}" class="form-control text-uppercase placa w-100" required placeholder="2481KLM">
            <div class="form-text">3-4 dígitos + 3 letras</div></div>
        <div class="col-md-5"><label class="form-label">Propietario *</label><input name="propietario" value="{{ old('propietario', $vehiculo->propietario) }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">CI / NIT</label><input name="ci_nit" value="{{ old('ci_nit', $vehiculo->ci_nit) }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Teléfono</label><input name="telefono" value="{{ old('telefono', $vehiculo->telefono) }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Categoría *</label>
            <select name="categoria" class="form-select">@foreach (\App\Models\Tarifa::ETIQUETAS as $k => $t)
                <option value="{{ $k }}" @selected(old('categoria', $vehiculo->categoria) === $k)>{{ $t }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Ejes *</label><input type="number" min="2" max="9" name="ejes" value="{{ old('ejes', $vehiculo->ejes) }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Marca</label><input name="marca" value="{{ old('marca', $vehiculo->marca) }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Modelo</label><input name="modelo" value="{{ old('modelo', $vehiculo->modelo) }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Año</label><input type="number" name="anio" value="{{ old('anio', $vehiculo->anio) }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Color</label><input name="color" value="{{ old('color', $vehiculo->color) }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Saldo (Bs) *</label><input type="number" step="0.01" min="0" name="saldo" value="{{ old('saldo', $vehiculo->saldo) }}" class="form-control" required></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $vehiculo->activo))>
            <label class="form-check-label" for="activo">Activo</label></div></div>
        <div class="col-12"><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $vehiculo->observaciones) }}</textarea></div>
    </div>
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
        <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
@endsection
