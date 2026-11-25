@extends('layouts.app')
@section('titulo', $caseta->exists ? 'Editar caseta' : 'Nueva caseta')
@section('contenido')
<form method="POST" action="{{ $caseta->exists ? route('casetas.update', $caseta) : route('casetas.store') }}" class="card card-body shadow-sm" style="max-width: 760px">
    @csrf @if ($caseta->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Código</label><input name="codigo" value="{{ old('codigo', $caseta->codigo) }}" class="form-control text-uppercase" required placeholder="AUT-01"></div>
        <div class="col-md-9"><label class="form-label">Nombre</label><input name="nombre" value="{{ old('nombre', $caseta->nombre) }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Carretera</label><input name="carretera" value="{{ old('carretera', $caseta->carretera) }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Ubicación</label><input name="ubicacion" value="{{ old('ubicacion', $caseta->ubicacion) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Latitud</label><input name="latitud" value="{{ old('latitud', $caseta->latitud) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Longitud</label><input name="longitud" value="{{ old('longitud', $caseta->longitud) }}" class="form-control"></div>
        <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="activa" value="1" id="activa" @checked(old('activa', $caseta->activa))>
            <label class="form-check-label" for="activa">Activa</label></div></div>
    </div>
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button><a href="{{ route('casetas.index') }}" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
@endsection
