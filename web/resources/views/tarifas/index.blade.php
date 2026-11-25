@extends('layouts.app')
@section('titulo', 'Tarifas (Bs) por categoría y ejes')
@section('contenido')
<div class="d-flex mb-3"><a href="{{ route('tarifas.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Nueva tarifa</a></div>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Matriz tarifaria</strong> <span class="small text-secondary">— más ejes = más desgaste del asfalto = mayor tarifa (mínimo 2 ejes)</span></div>
    <div class="table-responsive">
        <table class="table table-bordered text-center align-middle mb-0">
            <thead class="table-light"><tr><th class="text-start">Categoría</th>@foreach ([2, 3, 4, 5, 6] as $e)<th>{{ $e === 6 ? '6+' : $e }} ejes</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($matriz as $cat => $filas)
                <tr><th class="text-start">{{ \App\Models\Tarifa::ETIQUETAS[$cat] ?? $cat }}</th>
                    @foreach ([2, 3, 4, 5, 6] as $e)
                        @php($t = $filas->firstWhere('ejes', $e))
                        <td>@if ($t)<a href="{{ route('tarifas.edit', $t) }}" class="text-decoration-none {{ $t->activa ? '' : 'text-secondary text-decoration-line-through' }}">Bs {{ number_format($t->monto, 2) }}</a>@else <span class="text-secondary">—</span> @endif</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="card shadow-sm">
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light small"><tr><th>Categoría</th><th>Ejes</th><th class="text-end">Monto</th><th>Descripción</th><th>Activa</th><th></th></tr></thead>
        <tbody>@foreach ($tarifas as $t)
            <tr><td class="text-capitalize">{{ $t->categoria }}</td><td>{{ $t->ejes_texto }}</td><td class="text-end">Bs {{ number_format($t->monto, 2) }}</td>
                <td class="small">{{ $t->descripcion }}</td><td>{{ $t->activa ? 'Sí' : 'No' }}</td>
                <td class="text-end text-nowrap"><a href="{{ route('tarifas.edit', $t) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('tarifas.destroy', $t) }}" class="d-inline" onsubmit="return confirm('¿Eliminar tarifa?')">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        @endforeach</tbody>
    </table></div>
</div>
@endsection
