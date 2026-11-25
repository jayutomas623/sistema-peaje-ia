@extends('layouts.app')
@section('titulo', $tarifa->exists ? 'Editar tarifa' : 'Nueva tarifa')
@section('contenido')
<form method="POST" action="{{ $tarifa->exists ? route('tarifas.update', $tarifa) : route('tarifas.store') }}" class="card card-body shadow-sm" style="max-width: 640px">
    @csrf @if ($tarifa->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Categoría</label>
            <select name="categoria" class="form-select">@foreach (\App\Models\Tarifa::ETIQUETAS as $k => $t)
                <option value="{{ $k }}" @selected(old('categoria', $tarifa->categoria) === $k)>{{ $t }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Ejes</label>
            <select name="ejes" class="form-select">@foreach ([2, 3, 4, 5, 6] as $e)<option value="{{ $e }}" @selected(old('ejes', $tarifa->ejes) == $e)>{{ $e === 6 ? '6 o más' : $e }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Monto (Bs)</label><input type="number" step="0.01" min="0" name="monto" value="{{ old('monto', $tarifa->monto) }}" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Descripción</label><input name="descripcion" value="{{ old('descripcion', $tarifa->descripcion) }}" class="form-control"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="activa" value="1" id="activa" @checked(old('activa', $tarifa->activa))>
            <label class="form-check-label" for="activa">Activa</label></div></div>
    </div>
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button><a href="{{ route('tarifas.index') }}" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
@endsection
