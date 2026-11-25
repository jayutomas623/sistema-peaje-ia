@extends('layouts.app')
@section('titulo', 'Usuarios · lista blanca de Google')
@section('contenido')
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Solo los correos de esta lista (y activos) pueden iniciar sesión con Google en la web y en la app móvil.
    Cualquier otra cuenta ve la pantalla <strong>Acceso denegado</strong>.</div>
<div class="d-flex mb-3"><a href="{{ route('usuarios.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-person-plus"></i> Autorizar correo</a></div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light small"><tr><th></th><th>Nombre</th><th>Correo de Google</th><th>Rol</th><th>Activo</th><th>Último acceso</th><th></th></tr></thead>
    <tbody>@foreach ($usuarios as $u)
        <tr><td>@if ($u->avatar)<img src="{{ $u->avatar }}" width="32" height="32" class="rounded-circle" referrerpolicy="no-referrer" alt="">@else<i class="bi bi-person-circle fs-4 text-secondary"></i>@endif</td>
            <td>{{ $u->nombre }}</td><td><code>{{ $u->email }}</code></td>
            <td><span class="badge {{ $u->esAdmin() ? 'text-bg-warning' : 'text-bg-info' }}">{{ $u->rol }}</span></td>
            <td>{!! $u->activo ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' !!}</td>
            <td class="small">{{ $u->ultimo_acceso_at?->format('d/m/Y H:i') ?? 'nunca' }}</td>
            <td class="text-end text-nowrap"><a href="{{ route('usuarios.edit', $u) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('usuarios.destroy', $u) }}" class="d-inline" onsubmit="return confirm('¿Quitar {{ $u->email }} de la lista blanca?')">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    @endforeach</tbody>
</table></div></div>
@endsection
