@extends('layouts.app')
@section('titulo', 'Vehículos registrados')
@section('contenido')
<div class="d-flex flex-wrap gap-2 mb-3">
    <form class="d-flex gap-2" method="GET">
        <input name="q" value="{{ $q }}" class="form-control" placeholder="Placa o propietario">
        <select name="categoria" class="form-select" style="max-width: 150px"><option value="">Categoría</option>
            @foreach (\App\Models\Tarifa::CATEGORIAS as $c)<option @selected(request('categoria') === $c)>{{ $c }}</option>@endforeach</select>
        <button class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    @if (auth()->user()->esAdmin())
        <a href="{{ route('vehiculos.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Nuevo vehículo</a>
    @else
        <span class="ms-auto small text-secondary align-self-center"><i class="bi bi-eye"></i> Modo consulta (operador)</span>
    @endif
</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 tabla-compacta">
            <thead class="table-light small"><tr><th>Foto</th><th>Placa</th><th>Propietario</th><th>Categoría</th><th class="text-center">Ejes</th>
                <th>Vehículo</th><th class="text-end">Saldo</th><th class="text-center">Pasos</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($vehiculos as $v)
                <tr>
                    <td>@if ($v->foto_referencia)<img src="{{ Storage::disk('public')->url($v->foto_referencia) }}" class="evidencia-mini" loading="lazy" alt=""
                            data-evidencia="{{ Storage::disk('public')->url($v->foto_referencia) }}" data-titulo="{{ $v->placa }}">@endif</td>
                    <td><a href="{{ route('vehiculos.show', $v) }}" class="text-decoration-none"><span class="placa">{{ $v->placa }}</span></a></td>
                    <td>{{ $v->propietario }}<div class="small text-secondary">{{ $v->ci_nit }}</div></td>
                    <td class="text-capitalize">{{ $v->categoria }}</td>
                    <td class="text-center">{{ $v->ejes }}</td>
                    <td class="small">{{ $v->marca }} {{ $v->modelo }} · {{ $v->color }}</td>
                    <td class="text-end {{ $v->saldo < 10 ? 'text-danger fw-bold' : '' }}">Bs {{ number_format($v->saldo, 2) }}</td>
                    <td class="text-center">{{ $v->pasos_count }}</td>
                    <td>{!! $v->activo ? '<span class="badge text-bg-success">activo</span>' : '<span class="badge text-bg-secondary">inactivo</span>' !!}</td>
                    <td class="text-end text-nowrap">
                        @if (auth()->user()->esAdmin())
                            <a href="{{ route('vehiculos.edit', $v) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('vehiculos.destroy', $v) }}" class="d-inline" onsubmit="return confirm('¿Eliminar el vehículo {{ $v->placa }}?')">
                                @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-secondary py-4">Sin vehículos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $vehiculos->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
