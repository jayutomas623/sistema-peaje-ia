@extends('layouts.app')
@section('titulo', 'Casetas de peaje')
@section('contenido')
<div class="d-flex mb-3"><a href="{{ route('casetas.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Nueva caseta</a></div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light small"><tr><th>ID</th><th>Código</th><th>Nombre</th><th>Carretera</th><th>Ubicación</th><th class="text-center">Pasos hoy</th><th class="text-center">Total</th><th>Estado</th><th></th></tr></thead>
    <tbody>@foreach ($casetas as $c)
        <tr><td class="text-secondary">{{ $c->id }}</td><td><code>{{ $c->codigo }}</code></td><td class="fw-semibold">{{ $c->nombre }}</td><td class="small">{{ $c->carretera }}</td>
            <td class="small">{{ $c->ubicacion }}@if ($c->latitud)<br><a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $c->latitud }}&mlon={{ $c->longitud }}#map=15/{{ $c->latitud }}/{{ $c->longitud }}">mapa</a>@endif</td>
            <td class="text-center">{{ $c->pasos_hoy_count }}</td><td class="text-center">{{ $c->pasos_count }}</td>
            <td>{!! $c->activa ? '<span class="badge text-bg-success">activa</span>' : '<span class="badge text-bg-secondary">inactiva</span>' !!}</td>
            <td class="text-end text-nowrap"><a href="{{ route('casetas.edit', $c) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('casetas.destroy', $c) }}" class="d-inline" onsubmit="return confirm('¿Eliminar caseta?')">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    @endforeach</tbody>
</table></div></div>
@endsection
