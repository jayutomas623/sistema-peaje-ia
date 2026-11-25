@extends('layouts.app')
@section('titulo', 'Historial de pasos')
@section('contenido')
<form class="card card-body shadow-sm mb-3" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small">Placa</label>
            <input name="placa" value="{{ $f['placa'] ?? '' }}" class="form-control" placeholder="1151DKL"></div>
        <div class="col-6 col-md-3"><label class="form-label small">Caseta</label>
            <select name="caseta_id" class="form-select"><option value="">Todas</option>
                @foreach ($casetas as $c)<option value="{{ $c->id }}" @selected(($f['caseta_id'] ?? '') == $c->id)>{{ $c->nombre }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Estado</label>
            <select name="estado" class="form-select"><option value="">Todos</option>
                @foreach (\App\Models\Paso::ETIQUETAS_ESTADO as $k => $t)<option value="{{ $k }}" @selected(($f['estado'] ?? '') === $k)>{{ $t }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-1"><label class="form-label small">Categoría</label>
            <select name="categoria" class="form-select"><option value="">—</option>
                @foreach (\App\Models\Tarifa::CATEGORIAS as $c)<option @selected(($f['categoria'] ?? '') === $c)>{{ $c }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-1"><label class="form-label small">Desde</label><input type="date" name="desde" value="{{ $f['desde'] ?? '' }}" class="form-control"></div>
        <div class="col-6 col-md-1"><label class="form-label small">Hasta</label><input type="date" name="hasta" value="{{ $f['hasta'] ?? '' }}" class="form-control"></div>
        <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filtrar</button>
            <a href="{{ route('pasos.index') }}" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header bg-white small text-secondary">{{ $pasos->total() }} pasos · recaudado en esta página: <strong>Bs {{ number_format($totales['monto'], 2) }}</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 tabla-compacta">
            <thead class="table-light small"><tr><th>#</th><th>Evidencia</th><th>Placa</th><th>Categoría</th><th class="text-center">Ejes</th>
                <th class="text-center">Pasajeros</th><th class="text-end">Monto</th><th>Estado</th><th>Caseta</th><th>Fecha</th><th>Origen</th></tr></thead>
            <tbody>
            @forelse ($pasos as $p)
                <tr>
                    <td class="small text-secondary">{{ $p->id }}</td>
                    <td>@if ($p->evidencia_url)<img src="{{ $p->evidencia_url }}" class="evidencia-mini" loading="lazy" alt="evidencia"
                            data-evidencia="{{ $p->evidencia_url }}" data-titulo="Paso #{{ $p->id }} · {{ $p->placa_detectada ?? 'ilegible' }}">@endif</td>
                    <td>@if ($p->placa_detectada)<span class="placa">{{ $p->placa_detectada }}</span>@else<span class="placa vacia">ilegible</span>@endif
                        @if ($p->vehiculo)<div class="small"><a href="{{ route('vehiculos.show', $p->vehiculo) }}">{{ $p->vehiculo->propietario }}</a></div>@endif
                        @if ($p->nota)<div class="small text-secondary">{{ $p->nota }}</div>@endif</td>
                    <td class="text-capitalize">{{ $p->categoria_cobrada ?? $p->categoria_detectada ?? '—' }}
                        @if ($p->categoria_confianza)<div class="small text-secondary">IA {{ $p->categoria_detectada }} {{ number_format($p->categoria_confianza, 2) }}</div>@endif</td>
                    <td class="text-center">{{ $p->ejes_cobrados ?? '—' }}<div class="small text-secondary">{{ $p->vista_vehiculo }}</div></td>
                    <td class="text-center">{{ $p->pasajeros ?? '—' }}</td>
                    <td class="text-end fw-semibold">Bs {{ number_format($p->monto, 2) }}</td>
                    <td><span class="badge-estado estado-{{ $p->estado }}">{{ $p->estado_texto }}</span></td>
                    <td class="small">{{ $p->caseta?->nombre }}</td>
                    <td class="small">{{ $p->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="small text-secondary">{{ $p->origen }}@if ($p->latencia_ms)<br>{{ number_format($p->latencia_ms, 0) }} ms @endif</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-secondary py-4">Sin resultados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $pasos->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
