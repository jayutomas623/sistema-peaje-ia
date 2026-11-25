@extends('layouts.app')
@section('titulo', 'Vehículo '.$vehiculo->placa)
@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            @if ($vehiculo->foto_referencia)
                <img src="{{ Storage::disk('public')->url($vehiculo->foto_referencia) }}" class="card-img-top" style="max-height: 320px; object-fit: cover" alt="">
            @endif
            <div class="card-body">
                <h2 class="h4"><span class="placa">{{ $vehiculo->placa }}</span></h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Propietario</dt><dd class="col-7">{{ $vehiculo->propietario }}</dd>
                    <dt class="col-5">CI / NIT</dt><dd class="col-7">{{ $vehiculo->ci_nit ?? '—' }}</dd>
                    <dt class="col-5">Categoría</dt><dd class="col-7 text-capitalize">{{ $vehiculo->categoria }} · {{ $vehiculo->ejes }} ejes</dd>
                    <dt class="col-5">Vehículo</dt><dd class="col-7">{{ $vehiculo->marca }} {{ $vehiculo->modelo }} {{ $vehiculo->anio }} · {{ $vehiculo->color }}</dd>
                    <dt class="col-5">Saldo</dt><dd class="col-7 fw-bold {{ $vehiculo->saldo < 10 ? 'text-danger' : 'text-success' }}">Bs {{ number_format($vehiculo->saldo, 2) }}</dd>
                    <dt class="col-5">Estado</dt><dd class="col-7">{{ $vehiculo->activo ? 'Activo' : 'Inactivo' }}</dd>
                </dl>
                @if ($vehiculo->observaciones)<p class="small text-secondary mt-2 mb-0">{{ $vehiculo->observaciones }}</p>@endif
            </div>
            @if (auth()->user()->esAdmin())
                <div class="card-footer bg-white"><a href="{{ route('vehiculos.edit', $vehiculo) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Editar</a></div>
            @endif
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Pasos del vehículo</strong></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light small"><tr><th>Fecha</th><th>Caseta</th><th>Evidencia</th><th>Ejes</th><th>Pasajeros</th><th class="text-end">Monto</th><th>Saldo</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse ($pasos as $p)
                        <tr>
                            <td class="small">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                            <td class="small">{{ $p->caseta?->nombre }}</td>
                            <td>@if ($p->evidencia_url)<img src="{{ $p->evidencia_url }}" class="evidencia-mini" data-evidencia="{{ $p->evidencia_url }}" alt="">@endif</td>
                            <td>{{ $p->ejes_cobrados }}</td><td>{{ $p->pasajeros ?? '—' }}</td>
                            <td class="text-end">Bs {{ number_format($p->monto, 2) }}</td>
                            <td class="small">@if ($p->saldo_anterior !== null) {{ number_format($p->saldo_anterior, 2) }} → {{ number_format($p->saldo_posterior, 2) }} @endif</td>
                            <td><span class="badge-estado estado-{{ $p->estado }}">{{ $p->estado_texto }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">Registrada sin paso.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">{{ $pasos->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</div>
@endsection
