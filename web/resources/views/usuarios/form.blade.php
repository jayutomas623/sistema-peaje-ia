@extends('layouts.app')
@section('titulo', $usuario->exists ? 'Editar usuario' : 'Autorizar correo de Google')
@section('contenido')
<form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}" class="card card-body shadow-sm" style="max-width: 640px">
    @csrf @if ($usuario->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-12"><label class="form-label">Nombre</label><input name="nombre" value="{{ old('nombre', $usuario->nombre) }}" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Correo de Google</label><input type="email" name="email" value="{{ old('email', $usuario->email) }}" class="form-control" required placeholder="usuario@gmail.com"></div>
        <div class="col-md-6"><label class="form-label">Rol</label>
            <select name="rol" class="form-select">
                <option value="operador" @selected(old('rol', $usuario->rol) === 'operador')>Operador (registra pasos y consulta placas)</option>
                <option value="administrador" @selected(old('rol', $usuario->rol) === 'administrador')>Administrador (CRUD y configuración)</option>
            </select></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $usuario->activo))>
            <label class="form-check-label" for="activo">Activo</label></div></div>
    </div>
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button><a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
@endsection
